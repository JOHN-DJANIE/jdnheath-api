<?php
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";
require_once "sms.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";
$decoded = verifyToken();
$userId = $decoded["id"];

if ($method === "GET" && $action === "upcoming") {
    $today = date("Y-m-d");
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND appointment_date >= ? AND status = 'scheduled' AND deleted_at IS NULL ORDER BY appointment_date ASC");
    $stmt->execute([$userId, $today]);
    echo json_encode(["appointments" => $stmt->fetchAll()]);
}

elseif ($method === "GET" && $action === "one") {
    $id = $_GET["id"] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ? AND deleted_at IS NULL");
    $stmt->execute([$id, $userId]);
    echo json_encode(["appointment" => $stmt->fetch()]);
}

elseif ($method === "GET") {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND deleted_at IS NULL ORDER BY appointment_date ASC");
    $stmt->execute([$userId]);
    echo json_encode(["appointments" => $stmt->fetchAll()]);
}

elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!($data["doctor_name"] ?? "") || !($data["appointment_date"] ?? "") || !($data["appointment_time"] ?? "")) {
        http_response_code(400);
        echo json_encode(["error" => "Doctor name, date and time are required."]);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_name, specialty, appointment_date, appointment_time, notes) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $data["doctor_name"], $data["specialty"] ?? null, $data["appointment_date"], $data["appointment_time"], $data["notes"] ?? null]);
    
    $apptId = $pdo->lastInsertId();
    // Send SMS confirmation
    $userStmt = $pdo->prepare("SELECT phone, name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch();
    if ($userInfo["phone"]) {
        sendAppointmentConfirmSMS($userInfo["phone"], $data["doctor_name"], $data["appointment_date"], $data["appointment_time"]);
    }
    echo json_encode(["message" => "Appointment booked.", "id" => $apptId]);
}

elseif ($method === "PUT") {
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE appointments SET doctor_name=?, specialty=?, appointment_date=?, appointment_time=?, status=?, notes=? WHERE id=? AND patient_id=?");
    $stmt->execute([$data["doctor_name"] ?? null, $data["specialty"] ?? null, $data["appointment_date"] ?? null, $data["appointment_time"] ?? null, $data["status"] ?? "scheduled", $data["notes"] ?? null, $id, $userId]);
    echo json_encode(["message" => "Appointment updated."]);
}

elseif ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    $stmt = $pdo->prepare("UPDATE appointments SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND patient_id = ?");
    $stmt->execute([$id, $userId]);
    echo json_encode(["message" => "Appointment deleted."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}



