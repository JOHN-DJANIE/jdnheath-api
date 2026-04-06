<?php
require_once "db.php";
require_once "auth_helper.php";

$password = "Test1234";
$errors = [];
if (strlen($password) < 8) $errors[] = "Too short";
if (!preg_match("/[A-Z]/", $password)) $errors[] = "No uppercase";
if (!preg_match("/[0-9]/", $password)) $errors[] = "No number";

$email = "newtest" . time() . "@gmail.com";
$hashed = password_hash($password, PASSWORD_BCRYPT);
$code = str_pad(rand(0, 999999), 6, "0", STR_PAD_LEFT);
$expires = time() + 86400;

if (empty($errors)) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, verification_code, verification_expires, is_verified) VALUES (?,?,?,?,?,?,?,0)");
    $stmt->execute(["Test User", $email, $hashed, "0244000000", "patient", $code, $expires]);
    echo json_encode(["message" => "Success!", "email" => $email, "password" => "Test1234", "code" => $code]);
} else {
    echo json_encode(["errors" => $errors]);
}
