<?php
require_once "db.php";
$users = $pdo->query("SELECT id, name, email, created_at FROM users")->fetchAll();
echo json_encode($users, JSON_PRETTY_PRINT);
