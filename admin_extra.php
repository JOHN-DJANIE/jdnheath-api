<?php
if (!isset($method) || !isset($action)) return;

if ($method === "GET" && $action === "claims") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT ic.*, u.name as patient_name, u.email as patient_email FROM insurance_claims ic LEFT JOIN users u ON ic.user_id = u.id ORDER BY ic.created_at DESC");
    $stmt->execute([]);
    echo json_encode(["claims" => $stmt->fetchAll()]); exit;
}
if ($method === "PUT" && $action === "claim") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Claim ID required"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE insurance_claims SET status = ?, notes = ? WHERE id = ?")->execute([$data["status"] ?? "pending", $data["notes"] ?? null, $id]);
    echo json_encode(["message" => "Claim updated."]); exit;
}
if ($method === "POST" && $action === "broadcast_sms") {
    verifyAdmin($pdo);
    require_once "sms.php";
    $data = json_decode(file_get_contents("php://input"), true);
    $message = $data["message"] ?? "";
    $target = $data["target"] ?? "all";
    if (!$message) { http_response_code(400); echo json_encode(["error" => "Message required."]); exit; }
    $empty = "";
    $sql = "SELECT phone, name FROM users WHERE phone IS NOT NULL AND phone != ?";
    if ($target === "verified") $sql .= " AND is_verified = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empty]);
    $users = $stmt->fetchAll();
    $sent = 0;
    foreach ($users as $user) {
        try { if (sendSMS($user["phone"], $message)) $sent++; } catch(Exception $e) {}
    }
    echo json_encode(["message" => "Broadcast sent.", "sent" => $sent, "total" => count($users)]); exit;
}
