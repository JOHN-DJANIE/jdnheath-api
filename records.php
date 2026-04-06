<?php
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

$method = $_SERVER["REQUEST_METHOD"];
$decoded = verifyToken();
$userId = $decoded["id"];

if ($method === "GET") {
    $type = $_GET["type"] ?? "";
    $sql = "SELECT * FROM health_records WHERE user_id = ?";
    $params = [$userId];
    if ($type) { $sql .= " AND record_type = ?"; $params[] = $type; }
    $sql .= " ORDER BY recorded_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["records" => $stmt->fetchAll()]);
}

elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!($data["record_type"] ?? "") || !($data["title"] ?? "")) { http_response_code(400); echo json_encode(["error" => "Type and title required."]); exit; }
    $stmt = $pdo->prepare("INSERT INTO health_records (user_id, record_type, title, description, value, unit) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $data["record_type"], $data["title"], $data["description"] ?? null, $data["value"] ?? null, $data["unit"] ?? null]);
    echo json_encode(["message" => "Record added.", "id" => $pdo->lastInsertId()]);
}

elseif ($method === "PUT") {
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE health_records SET record_type=?, title=?, description=?, value=?, unit=? WHERE id=? AND user_id=?");
    $stmt->execute([$data["record_type"] ?? null, $data["title"] ?? null, $data["description"] ?? null, $data["value"] ?? null, $data["unit"] ?? null, $id, $userId]);
    echo json_encode(["message" => "Record updated."]);
}

elseif ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    $stmt = $pdo->prepare("DELETE FROM health_records WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    echo json_encode(["message" => "Record deleted."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}
