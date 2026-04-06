<?php
require_once __DIR__ . "/vendor/autoload.php";
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

define("JWT_SECRET", "jdnhealth_gh_super_secret_key_for_jwt_authentication_2026_secure");

function generateToken($user) {
    $payload = [
        "id" => $user["id"],
        "email" => $user["email"],
        "role" => $user["role"] ?? "patient",
        "exp" => time() + (7 * 24 * 60 * 60)
    ];
    return JWT::encode($payload, JWT_SECRET, "HS256");
}

function verifyToken() {
    $headers = getallheaders();
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";
    if (!$auth || !str_starts_with($auth, "Bearer ")) {
        http_response_code(401);
        echo json_encode(["error" => "No token provided."]);
        exit;
    }
    $token = substr($auth, 7);
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, "HS256"));
        return (array) $decoded;
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode(["error" => "Invalid or expired token."]);
        exit;
    }
}
