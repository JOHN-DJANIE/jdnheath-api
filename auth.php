<?php
error_reporting(0);
ini_set("display_errors", 0);
require_once "cors.php";
require_once "sms.php";
require_once "db.php";
require_once "auth_helper.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

// Helper: log auth events
function logAuth($pdo, $userId, $action, $status = "success", $details = null) {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    $agent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";
    $pdo->prepare("INSERT INTO auth_logs (user_id, action, ip_address, user_agent, status, details) VALUES (?,?,?,?,?,?)")
        ->execute([$userId, $action, $ip, $agent, $status, $details]);
}

// Helper: generate 6-digit code
function generateCode() {
    return str_pad(rand(0, 999999), 6, "0", STR_PAD_LEFT);
}

// Helper: send SMS via Arkesel (Ghana SMS API)


//  REGISTER 
if ($method === "POST" && $action === "register") {
    $data = json_decode(file_get_contents("php://input"), true);
    $name     = trim($data["name"] ?? "");
    $email    = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";
    $phone    = trim($data["phone"] ?? "");
    $gender   = $data["gender"] ?? null;
    $role     = in_array($data["role"] ?? "", ["patient","doctor","admin"]) ? $data["role"] : "patient";
    $nhia     = trim($data["nhia_number"] ?? "");
    $dob      = $data["date_of_birth"] ?? null;

    // Validation
    if (!$name || !$email || !$password) {
        http_response_code(400);
        echo json_encode(["error" => "Name, email and password are required."]);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid email address."]);
        exit;
    }
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(["error" => "Password must be at least 8 characters."]);
        exit;
    }
    if (!preg_match("/[A-Z]/", $password)) {
        http_response_code(400);
        echo json_encode(["error" => "Password must contain at least one uppercase letter."]);
        exit;
    }
    if (!preg_match("/[0-9]/", $password)) {
        http_response_code(400);
        echo json_encode(["error" => "Password must contain at least one number."]);
        exit;
    }

    // Check existing email
    $existing = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $existing->execute([$email]);
    if ($existing->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Email already registered."]);
        exit;
    }

    // Check existing phone
    if ($phone) {
        $existingPhone = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $existingPhone->execute([$phone]);
        if ($existingPhone->fetch()) {
            http_response_code(409);
            echo json_encode(["error" => "Phone number already registered."]);
            exit;
        }
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
    $verifyCode = generateCode();
    $verifyExpires = time() + (24 * 60 * 60); // 24 hours

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, gender, role, nhia_number, date_of_birth, verification_code, verification_expires, is_verified) VALUES (?,?,?,?,?,?,?,?,?,?,0)");
    $stmt->execute([$name, $email, $hashed, $phone ?: null, $gender, $role, $nhia ?: null, $dob, $verifyCode, $verifyExpires]);
    $id = $pdo->lastInsertId();
    // Send SMS if phone provided
    if ($phone) {
        sendWelcomeSMS($phone, $name);
        sendVerificationSMS($phone, $verifyCode);
    }
    logAuth($pdo, $id, "register", "success", "New $role registered");

    $token = generateToken(["id" => $id, "email" => $email, "role" => $role]);

    echo json_encode([
        "message" => "Registration successful. Please verify your account.",
        "token" => $token,
        "user" => [
            "id" => $id, "name" => $name, "email" => $email,
            "role" => $role, "phone" => $phone,
            "is_verified" => false,
            "verification_required" => true,
        ],
        "verification_code" => null, // Removed - sent via SMS only
    ]);
}

//  LOGIN 
elseif ($method === "POST" && $action === "login") {
    $data = json_decode(file_get_contents("php://input"), true);
    $email    = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(["error" => "Email and password are required."]);
        exit;
    }

    $user = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $user->execute([$email]);
    $user = $user->fetch();

    if (!$user) {
        logAuth($pdo, null, "login", "failed", "Email not found: $email");
        http_response_code(401);
        echo json_encode(["error" => "Invalid email or password."]);
        exit;
    }

    // Check if account is locked
    if ($user["locked_until"] && time() < $user["locked_until"]) {
        $minutes = ceil(($user["locked_until"] - time()) / 60);
        http_response_code(423);
        echo json_encode(["error" => "Account locked. Try again in $minutes minutes."]);
        exit;
    }

    if (!password_verify($password, $user["password"])) {
        // Increment failed attempts
        $attempts = ($user["login_attempts"] ?? 0) + 1;
        $locked = $attempts >= 5 ? time() + (30 * 60) : null; // Lock for 30 mins after 5 attempts
        $pdo->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?")
            ->execute([$attempts, $locked, $user["id"]]);

        logAuth($pdo, $user["id"], "login", "failed", "Wrong password, attempt $attempts");
        http_response_code(401);
        echo json_encode([
            "error" => "Invalid email or password.",
            "attempts_remaining" => max(0, 5 - $attempts),
        ]);
        exit;
    }

    // Reset failed attempts on success
    $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$user["id"]]);

    // Check if 2FA is enabled
    if ($user["two_fa_enabled"]) {
        $twoFaCode = generateCode();
        $twoFaExpires = time() + (10 * 60); // 10 minutes
        $pdo->prepare("UPDATE users SET two_fa_code = ?, two_fa_expires = ? WHERE id = ?")
            ->execute([$twoFaCode, $twoFaExpires, $user["id"]]);

        if ($user["phone"]) {
            sendLoginCodeSMS($user["phone"], $twoFaCode);
        }

        logAuth($pdo, $user["id"], "login_2fa_sent", "success");
        echo json_encode([
            "two_fa_required" => true,
            "user_id" => $user["id"],
            "message" => "Verification code sent to your phone.",
            "two_fa_code" => $twoFaCode, // Remove in production
        ]);
        exit;
    }

    logAuth($pdo, $user["id"], "login", "success");
    $token = generateToken(["id" => $user["id"], "email" => $user["email"], "role" => $user["role"]]);

    echo json_encode([
        "message" => "Login successful.",
        "token" => $token,
        "user" => [
            "id" => $user["id"], "name" => $user["name"],
            "email" => $user["email"], "role" => $user["role"],
            "phone" => $user["phone"], "is_verified" => (bool)$user["is_verified"],
            "profile_photo" => $user["profile_photo"],
            "nhia_number" => $user["nhia_number"],
            "blood_type" => $user["blood_type"],
            "gender" => $user["gender"],
        ],
    ]);
}

//  VERIFY 2FA 
elseif ($method === "POST" && $action === "verify2fa") {
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = $data["user_id"] ?? null;
    $code   = $data["code"] ?? "";

    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$userId]);
    $user = $user->fetch();

    if (!$user || $user["two_fa_code"] !== $code) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid verification code."]);
        exit;
    }
    if (time() > $user["two_fa_expires"]) {
        http_response_code(401);
        echo json_encode(["error" => "Verification code expired."]);
        exit;
    }

    $pdo->prepare("UPDATE users SET two_fa_code = NULL, two_fa_expires = NULL, last_login = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$user["id"]]);

    logAuth($pdo, $user["id"], "login_2fa_success", "success");
    $token = generateToken(["id" => $user["id"], "email" => $user["email"], "role" => $user["role"]]);

    echo json_encode([
        "message" => "Login successful.",
        "token" => $token,
        "user" => [
            "id" => $user["id"], "name" => $user["name"],
            "email" => $user["email"], "role" => $user["role"],
            "phone" => $user["phone"], "is_verified" => (bool)$user["is_verified"],
        ],
    ]);
}

//  VERIFY ACCOUNT 
elseif ($method === "POST" && $action === "verify") {
    $data = json_decode(file_get_contents("php://input"), true);
    $code = $data["code"] ?? "";
    $decoded = verifyToken();

    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$decoded["id"]]);
    $user = $user->fetch();

    if (!$user) { http_response_code(404); echo json_encode(["error" => "User not found."]); exit; }
    if ($user["verification_code"] !== $code) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid verification code."]);
        exit;
    }
    if (time() > $user["verification_expires"]) {
        http_response_code(400);
        echo json_encode(["error" => "Verification code expired. Request a new one."]);
        exit;
    }

    $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?")
        ->execute([$user["id"]]);

    logAuth($pdo, $user["id"], "account_verified", "success");
    echo json_encode(["message" => "Account verified successfully!"]);
}

//  RESEND VERIFICATION 
elseif ($method === "POST" && $action === "resend_verification") {
    $decoded = verifyToken();
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$decoded["id"]]);
    $user = $user->fetch();

    if (!$user) { http_response_code(404); echo json_encode(["error" => "User not found."]); exit; }
    if ($user["is_verified"]) { echo json_encode(["message" => "Account already verified."]); exit; }

    $code = generateCode();
    $expires = time() + (24 * 60 * 60);
    $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?")
        ->execute([$code, $expires, $user["id"]]);

    if ($user["phone"]) sendVerificationSMS($user["phone"], $code);

    echo json_encode(["message" => "Verification code sent.", "code" => $code]);
}

//  FORGOT PASSWORD 
elseif ($method === "POST" && $action === "forgot_password") {
    $data = json_decode(file_get_contents("php://input"), true);
    $phone = trim($data["phone"] ?? "");
    $email = trim($data["email"] ?? "");

    $user = null;
    if ($phone) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
    } elseif ($email) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        // Don't reveal if user exists
        echo json_encode(["message" => "If an account exists, a reset code has been sent."]);
        exit;
    }

    $code = generateCode();
    $expires = time() + (15 * 60); // 15 minutes
    $pdo->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE id = ?")
        ->execute([$code, $expires, $user["id"]]);

    if ($user["phone"]) sendPasswordResetSMS($user["phone"], $code);

    logAuth($pdo, $user["id"], "forgot_password", "success");
    echo json_encode(["message" => "Reset code sent to your phone.", "reset_code" => $code, "user_id" => $user["id"]]);
}

//  RESET PASSWORD 
elseif ($method === "POST" && $action === "reset_password") {
    $data = json_decode(file_get_contents("php://input"), true);
    $userId      = $data["user_id"] ?? null;
    $code        = $data["code"] ?? "";
    $newPassword = $data["new_password"] ?? "";

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(["error" => "Password must be at least 8 characters."]);
        exit;
    }

    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$userId]);
    $user = $user->fetch();

    if (!$user || $user["reset_code"] !== $code) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid reset code."]);
        exit;
    }
    if (time() > $user["reset_expires"]) {
        http_response_code(400);
        echo json_encode(["error" => "Reset code expired."]);
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ["cost" => 12]);
    $pdo->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expires = NULL, login_attempts = 0, locked_until = NULL WHERE id = ?")
        ->execute([$hashed, $user["id"]]);

    logAuth($pdo, $user["id"], "password_reset", "success");
    echo json_encode(["message" => "Password reset successful. Please log in."]);
}

//  CHANGE PASSWORD 
elseif ($method === "POST" && $action === "change_password") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $currentPassword = $data["current_password"] ?? "";
    $newPassword     = $data["new_password"] ?? "";

    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$decoded["id"]]);
    $user = $user->fetch();

    if (!password_verify($currentPassword, $user["password"])) {
        http_response_code(401);
        echo json_encode(["error" => "Current password is incorrect."]);
        exit;
    }
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(["error" => "New password must be at least 8 characters."]);
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ["cost" => 12]);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user["id"]]);

    logAuth($pdo, $user["id"], "password_changed", "success");
    echo json_encode(["message" => "Password changed successfully."]);
}

//  GET PROFILE 
elseif ($method === "GET" && $action === "me") {
    $decoded = verifyToken();
    $user = $pdo->prepare("SELECT id, name, email, role, phone, date_of_birth, gender, nhia_number, profile_photo, blood_type, allergies, address, emergency_contact, is_verified, two_fa_enabled, last_login, created_at FROM users WHERE id = ?");
    $user->execute([$decoded["id"]]);
    $user = $user->fetch();
    if (!$user) { http_response_code(404); echo json_encode(["error" => "User not found."]); exit; }
    $user["is_verified"] = (bool)$user["is_verified"];
    $user["two_fa_enabled"] = (bool)$user["two_fa_enabled"];
    echo json_encode(["user" => $user]);
}

//  UPDATE PROFILE 
elseif ($method === "PUT" && $action === "me") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);

    $pdo->prepare("UPDATE users SET name=?, phone=?, date_of_birth=?, gender=?, nhia_number=?, blood_type=?, allergies=?, address=?, emergency_contact=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
        ->execute([
            $data["name"] ?? null, $data["phone"] ?? null,
            $data["date_of_birth"] ?? null, $data["gender"] ?? null,
            $data["nhia_number"] ?? null, $data["blood_type"] ?? null,
            $data["allergies"] ?? null, $data["address"] ?? null,
            $data["emergency_contact"] ?? null, $decoded["id"],
        ]);

    logAuth($pdo, $decoded["id"], "profile_updated", "success");
    echo json_encode(["message" => "Profile updated successfully."]);
}

//  TOGGLE 2FA 
elseif ($method === "POST" && $action === "toggle2fa") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $enable = $data["enable"] ?? false;

    $user = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
    $user->execute([$decoded["id"]]);
    $user = $user->fetch();

    if ($enable && !$user["phone"]) {
        http_response_code(400);
        echo json_encode(["error" => "You need a phone number to enable 2FA."]);
        exit;
    }

    $pdo->prepare("UPDATE users SET two_fa_enabled = ? WHERE id = ?")->execute([$enable ? 1 : 0, $decoded["id"]]);
    logAuth($pdo, $decoded["id"], $enable ? "2fa_enabled" : "2fa_disabled", "success");
    echo json_encode(["message" => $enable ? "Two-factor authentication enabled." : "Two-factor authentication disabled."]);
}

//  UPLOAD PHOTO 
elseif ($method === "POST" && $action === "upload_photo") {
    $decoded = verifyToken();

    if (!isset($_FILES["photo"])) {
        http_response_code(400);
        echo json_encode(["error" => "No photo uploaded."]);
        exit;
    }

    $file = $_FILES["photo"];
    $allowedTypes = ["image/jpeg", "image/png", "image/webp"];
    if (!in_array($file["type"], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(["error" => "Only JPG, PNG and WebP images are allowed."]);
        exit;
    }
    if ($file["size"] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(["error" => "Image must be under 2MB."]);
        exit;
    }

    $uploadDir = __DIR__ . "/uploads/photos/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
    $filename = "user_" . $decoded["id"] . "_" . time() . "." . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file["tmp_name"], $filepath)) {
        $url = "/jdnhealth-api/uploads/photos/" . $filename;
        $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$url, $decoded["id"]]);
        echo json_encode(["message" => "Photo uploaded.", "url" => $url]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to upload photo."]);
    }
}

//  GET AUTH LOGS 
elseif ($method === "GET" && $action === "logs") {
    $decoded = verifyToken();
    $logs = $pdo->prepare("SELECT action, status, ip_address, details, created_at FROM auth_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $logs->execute([$decoded["id"]]);
    echo json_encode(["logs" => $logs->fetchAll()]);
}

//  DELETE ACCOUNT 
elseif ($method === "DELETE" && $action === "me") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $password = $data["password"] ?? "";

    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$decoded["id"]]);
    $user = $user->fetch();

    if (!password_verify($password, $user["password"])) {
        http_response_code(401);
        echo json_encode(["error" => "Incorrect password."]);
        exit;
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$decoded["id"]]);
    logAuth($pdo, $decoded["id"], "account_deleted", "success");
    echo json_encode(["message" => "Account deleted."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}




