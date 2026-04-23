<?php
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";
require_once "sms.php";

$method = $_SERVER["REQUEST_METHOD"];
$decoded = verifyToken();
$userId = $decoded["id"];

if ($method === "GET") {
    $stmt = $pdo->prepare("SELECT c.*, d.name as doctor_name, d.specialty, h.name as hospital_name FROM consultations c LEFT JOIN doctors d ON c.doctor_id = d.id LEFT JOIN hospitals h ON c.hospital_id = h.id WHERE c.patient_id = ? ORDER BY c.created_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(["consultations" => $stmt->fetchAll()]);
} elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("INSERT INTO consultations (patient_id, doctor_id, hospital_id, consultation_type, appointment_date, appointment_time, symptoms, notes, total_price) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$userId, $data["doctor_id"] ?? null, $data["hospital_id"] ?? null, $data["consultation_type"] ?? "video", $data["appointment_date"] ?? null, $data["appointment_time"] ?? null, $data["symptoms"] ?? null, $data["notes"] ?? null, $data["total_price"] ?? 0]);
    
    $consultId = $pdo->lastInsertId();
    // Send SMS confirmation
    $userStmt = $pdo->prepare("SELECT phone, name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch();
    if ($data["doctor_id"] && $userInfo["phone"]) {
        $docStmt = $pdo->prepare("SELECT name FROM doctors WHERE id = ?");
        $docStmt->execute([$data["doctor_id"]]);
        $doc = $docStmt->fetch();
        sendAppointmentConfirmSMS($userInfo["phone"], $doc["name"], $data["appointment_date"] ?? "TBD", $data["appointment_time"] ?? "TBD");
    }
    echo json_encode(["message" => "Consultation booked!", "id" => $consultId]);
} elseif ($method === "PUT") {
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE consultations SET status = ? WHERE id = ? AND patient_id = ?");
    $stmt->execute([$data["status"] ?? "cancelled", $id, $userId]);
    echo json_encode(["message" => "Updated."]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}


