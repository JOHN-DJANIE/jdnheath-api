<?php
require_once "db.php";
$pdo->prepare("UPDATE consultations SET total_price = 180.00 WHERE id = 11 AND total_price > 10000")->execute();
$rows = $pdo->prepare("SELECT id, doctor_id, patient_id, status, total_price, appointment_date FROM consultations ORDER BY id");
$rows->execute();
echo json_encode(["consultations" => $rows->fetchAll()]);
