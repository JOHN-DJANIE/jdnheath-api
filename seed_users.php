<?php
require_once "db.php";
$password = password_hash("Test1234", PASSWORD_DEFAULT);
$adminpass = password_hash("Admin1234", PASSWORD_DEFAULT);
try {
    $pdo->exec("DELETE FROM users WHERE email IN ('nidjanie@gmail.com','admin@jdnhealth.com')");
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, is_verified) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(["Nii-Kweye Djanie", "nidjanie@gmail.com", $password, "+233244000000", true]);
    echo json_encode(["message" => "Test user created!", "email" => "nidjanie@gmail.com", "password" => "Test1234"]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}