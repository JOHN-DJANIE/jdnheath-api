<?php
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";
$decoded = verifyToken();
$userId = $decoded["id"];

if ($method === "GET" && $action === "latest") {
    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    echo json_encode(["vitals" => $stmt->fetch() ?: null]);
}

elseif ($method === "GET") {
    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE user_id = ? ORDER BY recorded_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(["vitals" => $stmt->fetchAll()]);
}

elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $w = $data["weight"] ?? null;
    $h = $data["height"] ?? null;
    $bmi = ($w && $h) ? round($w / (($h/100) ** 2), 1) : null;

    $stmt = $pdo->prepare("INSERT INTO vitals (user_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, temperature, weight, height, bmi, blood_sugar, oxygen_saturation) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$userId, $data["blood_pressure_systolic"] ?? null, $data["blood_pressure_diastolic"] ?? null, $data["heart_rate"] ?? null, $data["temperature"] ?? null, $w, $h, $bmi, $data["blood_sugar"] ?? null, $data["oxygen_saturation"] ?? null]);
    echo json_encode(["message" => "Vitals recorded.", "id" => $pdo->lastInsertId(), "bmi" => $bmi]);
}

elseif ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    $stmt = $pdo->prepare("DELETE FROM vitals WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    echo json_encode(["message" => "Deleted."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}
