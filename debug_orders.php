<?php
require_once "cors.php";
require_once "db.php";
$stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'consultations' ORDER BY ordinal_position");
$stmt->execute([]);
echo json_encode(["columns" => array_column($stmt->fetchAll(), "column_name")]);
