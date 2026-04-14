<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
require_once "cors.php";
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$name  = trim($data["name"] ?? "TestUser");
$email = trim($data["email"] ?? "test".time()."@test.com");
$password = "Test1234";
$role = "patient";
$hashed = password_hash($password, PASSWORD_BCRYPT);

try {
    // Check existing
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) { echo json_encode(["error" => "Email exists"]); exit; }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?,?,?,?,?) RETURNING id");
    $stmt->execute([$name, $email, $hashed, $role, false]);
    $row = $stmt->fetch();
    echo json_encode(["message" => "Registered!", "id" => $row["id"] ?? "no id"]);
} catch(Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}