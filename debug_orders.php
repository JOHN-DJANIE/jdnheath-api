<?php
require_once "cors.php";
require_once "db.php";
// Check all order-related tables
$stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE '%order%'");
$stmt->execute([]);
$tables = array_column($stmt->fetchAll(), "table_name");

// Count rows in each
$counts = [];
foreach ($tables as $t) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM $t");
    $c->execute([]);
    $counts[$t] = $c->fetchColumn();
}
echo json_encode(["order_tables" => $tables, "counts" => $counts]);
