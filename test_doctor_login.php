<?php
require_once "db.php";
require_once "auth_helper.php";

$stmt = $pdo->prepare("SELECT id, name, email FROM doctors WHERE email = ?");
$stmt->execute(["dr.ama.owusu@jdnhealth.com"]);
$doctor = $stmt->fetch();

$token = generateToken(["id" => $doctor["id"], "email" => $doctor["email"], "role" => "doctor"]);
$decoded = (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key("jdnhealth_gh_super_secret_key_for_jwt_authentication_2026_secure", "HS256"));

echo json_encode([
    "doctor_id" => $doctor["id"],
    "token_id" => $decoded["id"],
    "match" => $doctor["id"] == $decoded["id"]
]);
