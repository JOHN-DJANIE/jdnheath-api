<?php
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

if ($method === "POST" && $action === "login") {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";
    if (!$email || !$password) { http_response_code(400); echo json_encode(["error" => "Email and password required."]); exit; }
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE email = ?");
    $stmt->execute([$email]);
    $doctor = $stmt->fetch();
    if (!$doctor || !password_verify($password, $doctor["password"])) { http_response_code(401); echo json_encode(["error" => "Invalid email or password."]); exit; }
    $token = generateToken(["id" => $doctor["id"], "email" => $doctor["email"], "role" => "doctor"]);
    echo json_encode(["message" => "Login successful.", "token" => $token, "doctor" => ["id" => $doctor["id"], "name" => $doctor["name"], "email" => $doctor["email"], "specialty" => $doctor["specialty"], "hospital" => $doctor["hospital"], "avatar" => $doctor["avatar"], "consultation_fee" => $doctor["consultation_fee"], "working_days" => $doctor["working_days"], "working_hours_start" => $doctor["working_hours_start"], "working_hours_end" => $doctor["working_hours_end"], "max_patients_per_day" => $doctor["max_patients_per_day"], "bio" => $doctor["bio"], "availability" => $doctor["availability"], "rating" => $doctor["rating"], "reviews" => $doctor["reviews"]]]);
}
elseif ($method === "GET" && $action === "me") {
    $decoded = verifyToken();
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
    $stmt->execute([$decoded["id"]]);
    $doctor = $stmt->fetch();
    if (!$doctor) { http_response_code(404); echo json_encode(["error" => "Not found."]); exit; }
    unset($doctor["password"]);
    echo json_encode(["doctor" => $doctor]);
}
elseif ($method === "PUT" && $action === "me") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE doctors SET bio=?, consultation_fee=?, working_days=?, working_hours_start=?, working_hours_end=?, max_patients_per_day=?, availability=? WHERE id=?")->execute([$data["bio"] ?? null, $data["consultation_fee"] ?? null, $data["working_days"] ?? null, $data["working_hours_start"] ?? null, $data["working_hours_end"] ?? null, $data["max_patients_per_day"] ?? null, $data["availability"] ?? null, $decoded["id"]]);
    echo json_encode(["message" => "Profile updated."]);
}
elseif ($method === "GET" && $action === "stats") {
    $decoded = verifyToken();
    $today = date("Y-m-d");
    $t = $pdo->prepare("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = ? AND appointment_date = ?"); $t->execute([$decoded["id"], $today]);
    $p = $pdo->prepare("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = ? AND status = ?"); $p->execute([$decoded["id"], "pending"]);
    $pts = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM consultations WHERE doctor_id = ?"); $pts->execute([$decoded["id"]]);
    $earn = $pdo->prepare("SELECT SUM(total_price) as total FROM consultations WHERE doctor_id = ? AND status = ?"); $earn->execute([$decoded["id"], "completed"]);
    echo json_encode(["today_appointments" => $t->fetch()["count"], "pending_appointments" => $p->fetch()["count"], "total_patients" => $pts->fetch()["count"], "total_earnings" => $earn->fetch()["total"] ?? 0]);
}
elseif ($method === "GET" && $action === "appointments") {
    $decoded = verifyToken();
    $status = $_GET["status"] ?? "";
    $sql = "SELECT c.*, u.name as patient_name, u.phone as patient_phone, u.email as patient_email, u.blood_type, u.allergies, u.date_of_birth FROM consultations c JOIN users u ON c.user_id = u.id WHERE c.doctor_id = ?";
    $params = [$decoded["id"]];
    if ($status) { $sql .= " AND c.status = ?"; $params[] = $status; }
    $sql .= " ORDER BY c.appointment_date ASC, c.appointment_time ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    echo json_encode(["appointments" => $stmt->fetchAll()]);
}
elseif ($method === "PUT" && $action === "appointment") {
    $decoded = verifyToken();
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE consultations SET status = ? WHERE id = ? AND doctor_id = ?")->execute([$data["status"] ?? "confirmed", $id, $decoded["id"]]);
    echo json_encode(["message" => "Updated."]);
}
elseif ($method === "POST" && $action === "prescription") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("INSERT INTO prescriptions (doctor_id, patient_id, consultation_id, diagnosis, medications, instructions, follow_up_date) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$decoded["id"], $data["patient_id"], $data["consultation_id"] ?? null, $data["diagnosis"] ?? null, $data["medications"] ?? null, $data["instructions"] ?? null, $data["follow_up_date"] ?? null]);
    echo json_encode(["message" => "Prescription written.", "id" => $pdo->lastInsertId()]);
}
elseif ($method === "GET" && $action === "prescriptions") {
    $decoded = verifyToken();
    $stmt = $pdo->prepare("SELECT p.*, u.name as patient_name FROM prescriptions p JOIN users u ON p.patient_id = u.id WHERE p.doctor_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$decoded["id"]]);
    echo json_encode(["prescriptions" => $stmt->fetchAll()]);
}
elseif ($method === "GET" && $action === "earnings") {
    $decoded = verifyToken();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_consultations, SUM(total_price) as total_earnings FROM consultations WHERE doctor_id = ? AND status = ?");
    $stmt->execute([$decoded["id"], "completed"]);
    $stats = $stmt->fetch();
    echo json_encode(["stats" => $stats, "monthly" => []]);
}
elseif ($method === "POST" && $action === "availability") {
    $decoded = verifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $date = $data["date"] ?? null; $slots = $data["slots"] ?? [];
    if (!$date || empty($slots)) { http_response_code(400); echo json_encode(["error" => "Date and slots required."]); exit; }
    $pdo->prepare("DELETE FROM availability_slots WHERE doctor_id = ? AND date = ?")->execute([$decoded["id"], $date]);
    $stmt = $pdo->prepare("INSERT INTO availability_slots (doctor_id, date, time_slot) VALUES (?,?,?)");
    foreach ($slots as $slot) { $stmt->execute([$decoded["id"], $date, $slot]); }
    echo json_encode(["message" => "Availability updated."]);
}
elseif ($method === "GET" && $action === "availability") {
    $decoded = verifyToken();
    $date = $_GET["date"] ?? date("Y-m-d");
    $stmt = $pdo->prepare("SELECT * FROM availability_slots WHERE doctor_id = ? AND date = ? ORDER BY time_slot");
    $stmt->execute([$decoded["id"], $date]);
    echo json_encode(["slots" => $stmt->fetchAll()]);
}
else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}

