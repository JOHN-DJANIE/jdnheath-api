<?php
require_once "db.php";
$pdo->exec("DELETE FROM users WHERE id IN (8,9)");
$users = $pdo->query("SELECT id, name, email, phone FROM users ORDER BY id")->fetchAll();
echo json_encode(["remaining" => $users, "message" => "Cleaned up!"]);
