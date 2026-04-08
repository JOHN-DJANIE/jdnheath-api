<?php
$databaseUrl = getenv("DATABASE_URL") ?: "postgresql://neondb_owner:npg_wYIl1tr8KTWg@ep-proud-thunder-a471usl4.us-east-1.aws.neon.tech/neondb?sslmode=require";

// Parse the URL
$url = parse_url($databaseUrl);
$host = $url["host"];
$port = $url["port"] ?? 5432;
$db   = ltrim($url["path"], "/");
$user = $url["user"];
$pass = $url["pass"];

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$db;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}