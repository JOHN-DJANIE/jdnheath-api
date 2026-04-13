<?php
// Force HTTPS on production (Railway) only
$isLocal = in_array($_SERVER["REMOTE_ADDR"] ?? "", ["127.0.0.1", "::1"]) ||
           strpos($_SERVER["HTTP_HOST"] ?? "", "localhost") !== false;

if (!$isLocal && empty($_SERVER["HTTPS"]) && ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") !== "https") {
    $redirect = "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    header("Location: $redirect", true, 301);
    exit;
}

// HSTS header
if (!$isLocal) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Strict CORS allowlist — only known frontend domains
$allowed = [
    // Local development
    "http://localhost:5173",
    "http://localhost:5174",
    "http://localhost:3000",
    "http://127.0.0.1:5173",
    "http://127.0.0.1:3000",
    // Production frontend
    "https://jdnhealth.vercel.app",
    "https://www.jdnhealth.com.gh",
    "https://jdnhealth.com.gh",
];

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
} else if (!empty($origin)) {
    // Unknown origin — reject with 403
    http_response_code(403);
    echo json_encode(["error" => "Origin not allowed."]);
    exit;
}
// No origin header = direct API call (mobile app, Postman, server) — allow through

header("Content-Type: application/json");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}