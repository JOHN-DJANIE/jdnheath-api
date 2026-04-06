<?php
require_once "db.php";
$users = $pdo->query("SELECT id, name, email, phone FROM users ORDER BY id")->fetchAll();
echo json_encode($users, JSON_PRETTY_PRINT);
