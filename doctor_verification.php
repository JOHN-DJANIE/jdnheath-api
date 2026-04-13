<?php
error_reporting(0);
ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";
require_once "cloudinary.php";
require_once "push.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

// DOCTOR: Submit verification application
if ($method === "POST" && $action === "apply") {
    $decoded = verifyToken();
    $userId  = $decoded["id"];

    // Get or create doctor record linked to this user
    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$userId]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        http_response_code(404);
        echo json_encode(["error" => "Doctor profile not found. Please complete your profile first."]);
        exit;
    }

    $doctorId = $doctor["id"];
    $data     = json_decode(file_get_contents("php://input"), true);

    // Update doctor profile with application data
    $pdo->prepare("UPDATE doctors SET
        license_number       = ?,
        specialty            = ?,
        hospital             = ?,
        years_experience     = ?,
        education            = ?,
        certifications       = ?,
        bio                  = ?,
        phone                = ?,
        verification_status  = 'pending',
        submitted_at         = CURRENT_TIMESTAMP
        WHERE id = ?")
        ->execute([
            $data["license_number"]   ?? null,
            $data["specialty"]        ?? null,
            $data["hospital"]         ?? null,
            $data["years_experience"] ?? null,
            $data["education"]        ?? null,
            $data["certifications"]   ?? null,
            $data["bio"]              ?? null,
            $data["phone"]            ?? null,
            $doctorId,
        ]);

    // Notify admins
    $admins = $pdo->prepare("SELECT push_token FROM users WHERE role IN ('admin','superadmin') AND push_token IS NOT NULL");
    $admins->execute([]);
    $tokens = array_column($admins->fetchAll(), "push_token");
    if (!empty($tokens)) {
        sendBulkPushNotifications($tokens,
            "New Doctor Verification Request",
            "A doctor has submitted their verification documents for review.",
            ["type" => "doctor_verification_request", "doctor_id" => $doctorId]
        );
    }

    echo json_encode(["message" => "Verification application submitted successfully. Admin will review within 2-3 business days.", "doctor_id" => $doctorId]);
}

// DOCTOR: Upload verification document
elseif ($method === "POST" && $action === "upload_document") {
    $decoded  = verifyToken();
    $userId   = $decoded["id"];
    $docType  = $_GET["doc_type"] ?? "license"; // license, certificate, id

    $allowed = ["license", "certificate", "id"];
    if (!in_array($docType, $allowed)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid document type."]);
        exit;
    }

    if (!isset($_FILES["document"])) {
        http_response_code(400);
        echo json_encode(["error" => "No document uploaded."]);
        exit;
    }

    $file         = $_FILES["document"];
    $allowedTypes = ["image/jpeg", "image/png", "application/pdf"];
    if (!in_array($file["type"], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(["error" => "Only JPG, PNG or PDF files allowed."]);
        exit;
    }
    if ($file["size"] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(["error" => "File too large. Maximum 5MB."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$userId]);
    $doctor = $stmt->fetch();
    if (!$doctor) { http_response_code(404); echo json_encode(["error" => "Doctor profile not found."]); exit; }

    try {
        $publicId = "doctor_" . $doctor["id"] . "_" . $docType . "_" . time();
        $url      = uploadToCloudinary($file["tmp_name"], "jdnhealth/doctor_documents", $publicId);
        $col      = $docType . "_document";
        $pdo->prepare("UPDATE doctors SET $col = ? WHERE id = ?")->execute([$url, $doctor["id"]]);
        echo json_encode(["message" => "Document uploaded.", "url" => $url, "doc_type" => $docType]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Upload failed: " . $e->getMessage()]);
    }
}

// DOCTOR: Check verification status
elseif ($method === "GET" && $action === "status") {
    $decoded = verifyToken();
    $stmt    = $pdo->prepare("SELECT id, name, verification_status, is_verified, is_active, submitted_at, verified_at, verification_notes, license_document, certificate_document, id_document FROM doctors WHERE user_id = ?");
    $stmt->execute([$decoded["id"]]);
    $doctor = $stmt->fetch();
    if (!$doctor) { http_response_code(404); echo json_encode(["error" => "Doctor profile not found."]); exit; }
    echo json_encode(["doctor" => $doctor]);
}

// ADMIN: Get all pending verifications
elseif ($method === "GET" && $action === "pending") {
    require_once "auth_helper.php";
    $adminDecoded = verifyToken();
    if (!in_array($adminDecoded["role"], ["admin", "superadmin"])) {
        http_response_code(403); echo json_encode(["error" => "Admin access required."]); exit;
    }
    $stmt = $pdo->prepare("SELECT id, name, email, specialty, hospital, license_number, years_experience, education, certifications, verification_status, submitted_at, license_document, certificate_document, id_document, verification_notes FROM doctors WHERE verification_status = 'pending' ORDER BY submitted_at ASC");
    $stmt->execute([]);
    echo json_encode(["doctors" => $stmt->fetchAll()]);
}

// ADMIN: Approve or reject doctor
elseif ($method === "PUT" && $action === "review") {
    $adminDecoded = verifyToken();
    if (!in_array($adminDecoded["role"], ["admin", "superadmin"])) {
        http_response_code(403); echo json_encode(["error" => "Admin access required."]); exit;
    }

    $data     = json_decode(file_get_contents("php://input"), true);
    $doctorId = $_GET["id"] ?? null;
    $decision = $data["decision"] ?? ""; // approve or reject
    $notes    = $data["notes"] ?? "";

    if (!$doctorId || !in_array($decision, ["approve", "reject"])) {
        http_response_code(400);
        echo json_encode(["error" => "Doctor ID and decision (approve/reject) required."]);
        exit;
    }

    if ($decision === "approve") {
        $pdo->prepare("UPDATE doctors SET
            verification_status = 'approved',
            is_verified         = 1,
            is_active           = TRUE,
            verified_at         = CURRENT_TIMESTAMP,
            verified_by         = ?,
            verification_notes  = ?
            WHERE id = ?")
            ->execute([$adminDecoded["id"], $notes, $doctorId]);

        // Notify doctor
        $stmt = $pdo->prepare("SELECT u.push_token, d.name FROM doctors d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ?");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();
        if ($doctor && $doctor["push_token"]) {
            sendPushNotification($doctor["push_token"],
                "Verification Approved! 🎉",
                "Congratulations! Your doctor profile has been verified. You can now receive patient bookings.",
                ["type" => "doctor_approved"]
            );
        }

        // Log to admin_logs
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)")
            ->execute([$adminDecoded["id"], "doctor_approved", $_SERVER["REMOTE_ADDR"] ?? "unknown", "Doctor ID $doctorId approved"]);

        echo json_encode(["message" => "Doctor approved and activated successfully."]);
    } else {
        $pdo->prepare("UPDATE doctors SET
            verification_status = 'rejected',
            is_verified         = 0,
            is_active           = FALSE,
            verification_notes  = ?,
            verified_by         = ?
            WHERE id = ?")
            ->execute([$notes, $adminDecoded["id"], $doctorId]);

        // Notify doctor
        $stmt = $pdo->prepare("SELECT u.push_token FROM doctors d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ?");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();
        if ($doctor && $doctor["push_token"]) {
            sendPushNotification($doctor["push_token"],
                "Verification Update",
                "Your verification application needs attention. Please check your dashboard for details.",
                ["type" => "doctor_rejected"]
            );
        }

        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)")
            ->execute([$adminDecoded["id"], "doctor_rejected", $_SERVER["REMOTE_ADDR"] ?? "unknown", "Doctor ID $doctorId rejected"]);

        echo json_encode(["message" => "Doctor application rejected.", "notes" => $notes]);
    }
}

// ADMIN: Get all doctors with verification status
elseif ($method === "GET" && $action === "all") {
    $adminDecoded = verifyToken();
    if (!in_array($adminDecoded["role"], ["admin", "superadmin"])) {
        http_response_code(403); echo json_encode(["error" => "Admin access required."]); exit;
    }
    $status = $_GET["status"] ?? "";
    $sql    = "SELECT id, name, email, specialty, hospital, verification_status, is_verified, is_active, submitted_at, verified_at FROM doctors";
    $params = [];
    if ($status) { $sql .= " WHERE verification_status = ?"; $params[] = $status; }
    $sql .= " ORDER BY submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["doctors" => $stmt->fetchAll()]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}