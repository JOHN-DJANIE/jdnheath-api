<?php
require_once "cors.php";
require_once "db.php";
// Get column names of orders table
$stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'orders' ORDER BY ordinal_position");
$stmt->execute([]);
echo json_encode(["columns" => array_column($stmt->fetchAll(), "column_name")]);
