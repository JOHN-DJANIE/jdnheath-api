<?php
require_once "db.php";
try {
    $stmt = $pdo->prepare("SELECT id, email FROM users LIMIT 3");
    $stmt->execute();
    $users = $stmt->fetchAll();
    echo json_encode(["connected" => true, "users" => $users]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}