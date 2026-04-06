<?php
require_once "db.php";

// Create a new confirmed video appointment for Dr. Ama Owusu (doctor_id=1) with patient (user_id=10)
$stmt = $pdo->prepare("INSERT INTO consultations (user_id, doctor_id, consultation_type, appointment_date, appointment_time, status, total_price, symptoms) VALUES (?,?,?,?,?,?,?,?)");
$stmt->execute([10, 1, "video", date("Y-m-d"), date("H:i", strtotime("+1 hour")), "confirmed", 300, "Headache and fever"]);

$id = $pdo->lastInsertId();
echo json_encode(["message" => "Confirmed video appointment created!", "id" => $id]);
