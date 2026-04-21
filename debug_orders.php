<?php
require_once "cors.php";
require_once "db.php";
$count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$sample = $pdo->query("SELECT * FROM orders LIMIT 3")->fetchAll();
echo json_encode(["total_orders" => $count, "sample" => $sample]);
