<?php
require_once "db.php";
$consultations = $pdo->query("SELECT id, user_id, doctor_id, consultation_type, appointment_date, appointment_time, status, created_at FROM consultations ORDER BY id DESC LIMIT 10")->fetchAll();
echo json_encode($consultations, JSON_PRETTY_PRINT);
