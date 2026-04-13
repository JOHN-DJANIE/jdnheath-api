<?php
// Rate limiting configuration
$RATE_LIMITS = [
    "login"           => ["max" => 5,  "window" => 900],  // 5 attempts per 15 mins
    "register"        => ["max" => 3,  "window" => 3600], // 3 per hour
    "forgot_password" => ["max" => 3,  "window" => 3600], // 3 per hour
    "verify2fa"       => ["max" => 10, "window" => 600],  // 10 per 10 mins
    "default"         => ["max" => 30, "window" => 60],   // 30 per min
];

function getRateLimit($action, $limits) {
    return $limits[$action] ?? $limits["default"];
}

function getClientIP() {
    $headers = ["HTTP_CF_CONNECTING_IP", "HTTP_X_FORWARDED_FOR", "HTTP_X_REAL_IP", "REMOTE_ADDR"];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(",", $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return "unknown";
}

function checkRateLimit($pdo, $action, $limits) {
    $ip     = getClientIP();
    $limit  = getRateLimit($action, $limits);
    $max    = $limit["max"];
    $window = $limit["window"];
    $cutoff = date("Y-m-d H:i:s", time() - $window);

    // Clean old records
    $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND first_attempt < ?")
        ->execute([$ip, $action, $cutoff]);

    // Check current count
    $stmt = $pdo->prepare("SELECT attempts FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND first_attempt >= ?");
    $stmt->execute([$ip, $action, $cutoff]);
    $row = $stmt->fetch();

    if ($row) {
        if (intval($row["attempts"]) >= $max) {
            $retryAfter = $window / 60;
            http_response_code(429);
            echo json_encode([
                "error"       => "Too many attempts. Please try again in " . ceil($retryAfter) . " minutes.",
                "retry_after" => $window,
            ]);
            exit;
        }
        // Increment attempts
        $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1, last_attempt = CURRENT_TIMESTAMP WHERE ip_address = ? AND endpoint = ? AND first_attempt >= ?")
            ->execute([$ip, $action, $cutoff]);
    } else {
        // First attempt
        $pdo->prepare("INSERT INTO rate_limits (ip_address, endpoint, attempts) VALUES (?, ?, 1)")
            ->execute([$ip, $action]);
    }
}