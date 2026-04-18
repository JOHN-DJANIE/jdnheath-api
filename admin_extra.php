<?php
// GET CLAIMS
if ($method === "GET" && $action === "claims") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT ic.*, u.name as patient_name, u.email as patient_email FROM insurance_claims ic LEFT JOIN users u ON ic.user_id = u.id ORDER BY ic.created_at DESC");
    $stmt->execute([]);
    echo json_encode(["claims" => $stmt->fetchAll()]); exit;
}
// UPDATE CLAIM
if ($method === "PUT" && $action === "claim") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Claim ID required"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE insurance_claims SET status = ?, notes = ? WHERE id = ?")->execute([$data["status"] ?? "pending", $data["notes"] ?? null, $id]);
    echo json_encode(["message" => "Claim updated."]); exit;
}
// ADD HOSPITAL
if ($method === "POST" && $action === "hospital") {
    verifyAdmin($pdo);
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data["name"])) { http_response_code(400); echo json_encode(["error" => "Hospital name required"]); exit; }
    $stmt = $pdo->prepare("INSERT INTO hospitals (name, type, location, region, phone, email, departments, facilities, opening_hours, is_active) VALUES (?,?,?,?,?,?,?,?,?,TRUE)");
    $stmt->execute([$data["name"], $data["type"] ?? "Government Hospital", $data["location"] ?? "", $data["region"] ?? "Greater Accra", $data["phone"] ?? null, $data["email"] ?? null, $data["departments"] ?? null, $data["facilities"] ?? null, $data["opening_hours"] ?? "24/7"]);
    echo json_encode(["message" => "Hospital added.", "id" => $pdo->lastInsertId()]); exit;
}
// UPDATE HOSPITAL
if ($method === "PUT" && $action === "hospital") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Hospital ID required"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE hospitals SET name=?, type=?, location=?, region=?, phone=?, email=?, departments=?, facilities=?, opening_hours=? WHERE id=?")->execute([$data["name"], $data["type"] ?? "Government Hospital", $data["location"] ?? "", $data["region"] ?? "Greater Accra", $data["phone"] ?? null, $data["email"] ?? null, $data["departments"] ?? null, $data["facilities"] ?? null, $data["opening_hours"] ?? "24/7", $id]);
    echo json_encode(["message" => "Hospital updated."]); exit;
}
// DELETE HOSPITAL
if ($method === "DELETE" && $action === "hospital") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Hospital ID required"]); exit; }
    $pdo->prepare("DELETE FROM hospitals WHERE id = ?")->execute([$id]);
    echo json_encode(["message" => "Hospital deleted."]); exit;
}
// FIX BROADCAST
if ($method === "POST" && $action === "broadcast") {
    verifyAdmin($pdo);
    require_once "sms.php";
    $data = json_decode(file_get_contents("php://input"), true);
    $message = $data["message"] ?? "";
    $target = $data["target"] ?? "all";
    if (!$message) { http_response_code(400); echo json_encode(["error" => "Message required."]); exit; }
    $sql = "SELECT phone, name FROM users WHERE phone IS NOT NULL AND phone != ''";
    if ($target === "verified") $sql .= " AND is_verified = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([]);
    $users = $stmt->fetchAll();
    $sent = 0;
    foreach ($users as $user) {
        try { if (sendSMS($user["phone"], $message)) $sent++; } catch(Exception $e) {}
    }
    echo json_encode(["message" => "Broadcast sent.", "sent" => $sent, "total" => count($users)]); exit;
}
