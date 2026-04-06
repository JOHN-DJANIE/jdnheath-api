<?php
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

$method = $_SERVER["REQUEST_METHOD"];
$decoded = verifyToken();
$userId = $decoded["id"];

if ($method === "GET") {
    $activeOnly = $_GET["active"] ?? "";
    $sql = "SELECT * FROM medications WHERE user_id = ?";
    if ($activeOnly === "true") $sql .= " AND is_active = 1";
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    echo json_encode(["medications" => $stmt->fetchAll()]);
}

elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!($data["name"] ?? "")) { http_response_code(400); echo json_encode(["error" => "Name is required."]); exit; }
    $stmt = $pdo->prepare("INSERT INTO medications (user_id, name, dosage, frequency, start_date, end_date, notes) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$userId, $data["name"], $data["dosage"] ?? null, $data["frequency"] ?? null, $data["start_date"] ?? null, $data["end_date"] ?? null, $data["notes"] ?? null]);
    echo json_encode(["message" => "Medication added.", "id" => $pdo->lastInsertId()]);
}

elseif ($method === "PUT") {
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE medications SET name=?, dosage=?, frequency=?, start_date=?, end_date=?, notes=?, is_active=? WHERE id=? AND user_id=?");
    $stmt->execute([$data["name"] ?? null, $data["dosage"] ?? null, $data["frequency"] ?? null, $data["start_date"] ?? null, $data["end_date"] ?? null, $data["notes"] ?? null, $data["is_active"] ?? 1, $id, $userId]);
    echo json_encode(["message" => "Medication updated."]);
}

elseif ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    $stmt = $pdo->prepare("DELETE FROM medications WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    echo json_encode(["message" => "Medication deleted."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}
