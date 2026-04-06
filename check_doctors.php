<?php
require_once "db.php";
$doctors = $pdo->query("SELECT id, name, email, password, consultation_fee, is_verified FROM doctors ORDER BY id")->fetchAll();
echo json_encode($doctors, JSON_PRETTY_PRINT);
