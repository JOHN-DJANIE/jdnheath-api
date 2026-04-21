<?php
require_once "cors.php";
require_once "db.php";
$stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
$stmt->execute([]);
$tables = array_column($stmt->fetchAll(), "table_name");
$counts = [];
foreach ($tables as $t) {
    try {
        $c = $pdo->prepare("SELECT COUNT(*) FROM \"$t\"");
        $c->execute([]);
        $counts[$t] = intval($c->fetchColumn());
    } catch(Exception $e) { $counts[$t] = "error"; }
}
echo json_encode(["tables" => $counts]);
