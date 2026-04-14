<?php
require_once "db.php";
$stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
$stmt->execute([]);
echo json_encode(["tables" => array_column($stmt->fetchAll(), "table_name")]);