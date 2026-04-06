<?php
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

// VERIFY NHIS MEMBERSHIP
if ($method === "POST" && $action === "verify") {
    $decoded = verifyToken();
    $user_id = $decoded["id"];
    $data = json_decode(file_get_contents("php://input"), true);
    $nhia_number = trim($data["nhia_number"] ?? "");

    if (!$nhia_number) {
        http_response_code(400);
        echo json_encode(["error" => "NHIS number is required."]);
        exit;
    }

    // Validate NHIS number format (GHA-NHIA-XXXXXXXXX or plain digits)
    $clean = preg_replace('/[^A-Z0-9\-]/i', '', $nhia_number);
    if (strlen($clean) < 6) {
        http_response_code(400);
        echo json_encode(["error" => "Please enter a valid NHIS card number."]);
        exit;
    }

    // Check if already registered for this user
    $stmt = $pdo->prepare("SELECT * FROM nhia_members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        echo json_encode(["success" => true, "message" => "Already registered", "member" => $existing]);
        exit;
    }

    // Check if this NHIS number is used by another account
    $stmt = $pdo->prepare("SELECT id FROM nhia_members WHERE nhia_number = ?");
    $stmt->execute([$nhia_number]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "This NHIS number is already linked to another account."]);
        exit;
    }

    // Register new NHIS member
    $expiry = date('Y-m-d', strtotime('+1 year'));
    $stmt = $pdo->prepare("INSERT INTO nhia_members (user_id, nhia_number, membership_type, status, expiry_date, verified) VALUES (?, ?, 'standard', 'active', ?, 1)");
    $stmt->execute([$user_id, strtoupper($nhia_number), $expiry]);

    // Update user nhia_number
    $pdo->prepare("UPDATE users SET nhia_number = ? WHERE id = ?")->execute([strtoupper($nhia_number), $user_id]);

    $member = $pdo->prepare("SELECT * FROM nhia_members WHERE user_id = ?");
    $member->execute([$user_id]);
    echo json_encode(["success" => true, "message" => "NHIS membership verified successfully!", "member" => $member->fetch()]);
}

// GET NHIS STATUS
elseif ($method === "GET" && $action === "status") {
    $decoded = verifyToken();
    $user_id = $decoded["id"];
    $stmt = $pdo->prepare("SELECT * FROM nhia_members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();
    echo json_encode(["member" => $member ?? null]);
}

// SUBMIT CLAIM
elseif ($method === "POST" && $action === "claim") {
    $decoded = verifyToken();
    $user_id = $decoded["id"];
    $data = json_decode(file_get_contents("php://input"), true);

    $nhia_number = $data["nhia_number"] ?? "";
    $claim_type  = $data["claim_type"] ?? "";
    $amount      = floatval($data["amount"] ?? 0);
    $notes       = $data["notes"] ?? "";

    if (!$nhia_number || !$claim_type) {
        http_response_code(400);
        echo json_encode(["error" => "NHIS number and claim type are required."]);
        exit;
    }
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Please enter a valid claim amount."]);
        exit;
    }

    // Verify active membership
    $stmt = $pdo->prepare("SELECT * FROM nhia_members WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();
    if (!$member) {
        http_response_code(403);
        echo json_encode(["error" => "No active NHIS membership found. Please verify your card first."]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO insurance_claims (user_id, nhia_number, claim_type, amount, status, notes) VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([$user_id, $nhia_number, $claim_type, $amount, $notes]);
    $claim_id = $pdo->lastInsertId();

    echo json_encode(["success" => true, "message" => "Claim submitted successfully!", "claim_id" => $claim_id]);
}

// GET MY CLAIMS
elseif ($method === "GET" && $action === "claims") {
    $decoded = verifyToken();
    $user_id = $decoded["id"];
    $stmt = $pdo->prepare("SELECT * FROM insurance_claims WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    echo json_encode(["claims" => $stmt->fetchAll()]);
}

// ADMIN - GET ALL CLAIMS
elseif ($method === "GET" && $action === "admin_claims") {
    $decoded = verifyToken();
    $stmt = $pdo->query("SELECT ic.*, u.name as patient_name, u.email as patient_email FROM insurance_claims ic LEFT JOIN users u ON ic.user_id = u.id ORDER BY ic.created_at DESC");
    echo json_encode(["claims" => $stmt->fetchAll()]);
}

// ADMIN - UPDATE CLAIM STATUS
elseif ($method === "PUT" && $action === "update_claim") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $claim_id = $_GET["id"] ?? null;
    $status = $data["status"] ?? "";
    $notes = $data["notes"] ?? "";

    if (!$claim_id || !$status) {
        http_response_code(400);
        echo json_encode(["error" => "Claim ID and status required."]);
        exit;
    }
    $pdo->prepare("UPDATE insurance_claims SET status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $notes, $claim_id]);
    echo json_encode(["success" => true, "message" => "Claim updated!"]);
}

// GET NHIS COVERED SERVICES
elseif ($method === "GET" && $action === "covered") {
    echo json_encode(["services" => [
        ["name" => "General Consultation",    "coverage" => "80%", "max_amount" => 150],
        ["name" => "Specialist Consultation", "coverage" => "70%", "max_amount" => 250],
        ["name" => "Lab Tests",               "coverage" => "75%", "max_amount" => 200],
        ["name" => "Medicines (Essential)",   "coverage" => "90%", "max_amount" => 100],
        ["name" => "Maternity Care",          "coverage" => "100%","max_amount" => 500],
        ["name" => "Emergency Care",          "coverage" => "100%","max_amount" => 1000],
        ["name" => "Hospitalization",         "coverage" => "85%", "max_amount" => 2000],
        ["name" => "Mental Health",           "coverage" => "60%", "max_amount" => 200],
    ]]);
}

// UNLINK NHIS CARD
elseif ($method === "DELETE" && $action === "unlink") {
    $decoded = verifyToken();
    $user_id = $decoded["id"];
    $pdo->prepare("DELETE FROM nhia_members WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("UPDATE users SET nhia_number = NULL WHERE id = ?")->execute([$user_id]);
    echo json_encode(["success" => true, "message" => "NHIS card unlinked successfully."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}