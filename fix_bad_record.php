<?php
require_once "db.php";
// Fix the bad total_price on consultation id=11
$stmt = $pdo->prepare("UPDATE consultations SET total_price = 180.00 WHERE id = 11 AND total_price > 10000");
$stmt->execute();
$fixed = $stmt->rowCount();
// Show all consultations summary
$rows = $pdo->query("SELECT id, doctor_id, patient_id, status, total_price, appointment_date FROM consultations ORDER BY id")->fetchAll();
echo json_encode(["fixed_rows" => $fixed, "consultations" => $rows]);
