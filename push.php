<?php
// Send Expo push notifications
function sendPushNotification($token, $title, $body, $data = []) {
    if (!$token || !str_starts_with($token, "ExponentPushToken")) return false;

    $payload = [
        "to"    => $token,
        "title" => $title,
        "body"  => $body,
        "data"  => $data,
        "sound" => "default",
        "badge" => 1,
        "channelId" => "jdnhealth",
    ];

    $ch = curl_init("https://exp.host/--/api/v2/push/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Send to multiple tokens at once
function sendBulkPushNotifications($tokens, $title, $body, $data = []) {
    $tokens = array_filter($tokens, fn($t) => str_starts_with($t ?? "", "ExponentPushToken"));
    if (empty($tokens)) return [];

    $messages = array_map(fn($t) => [
        "to"        => $t,
        "title"     => $title,
        "body"      => $body,
        "data"      => $data,
        "sound"     => "default",
        "badge"     => 1,
        "channelId" => "jdnhealth",
    ], array_values($tokens));

    $ch = curl_init("https://exp.host/--/api/v2/push/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($messages),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Notification helpers
function notifyAppointmentConfirmed($pdo, $userId, $doctorName, $date, $time) {
    $stmt = $pdo->prepare("SELECT push_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && $user["push_token"]) {
        sendPushNotification($user["push_token"],
            "Appointment Confirmed! 🏥",
            "Your appointment with $doctorName on $date at $time is confirmed.",
            ["type" => "appointment_confirmed"]
        );
    }
}

function notifyOrderPlaced($pdo, $userId, $total) {
    $stmt = $pdo->prepare("SELECT push_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && $user["push_token"]) {
        sendPushNotification($user["push_token"],
            "Order Placed! 💊",
            "Your pharmacy order of GH $total has been received and is being processed.",
            ["type" => "order_placed"]
        );
    }
}

function notifyPaymentSuccess($pdo, $userId, $amount, $type) {
    $stmt = $pdo->prepare("SELECT push_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && $user["push_token"]) {
        sendPushNotification($user["push_token"],
            "Payment Successful! ✅",
            "GH $amount paid successfully for your $type.",
            ["type" => "payment_success"]
        );
    }
}

function notifyLabResult($pdo, $userId, $testName) {
    $stmt = $pdo->prepare("SELECT push_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && $user["push_token"]) {
        sendPushNotification($user["push_token"],
            "Lab Result Ready! 🔬",
            "Your $testName result is now available.",
            ["type" => "lab_result"]
        );
    }
}

function notifyConsultationConfirmed($pdo, $userId, $doctorName) {
    $stmt = $pdo->prepare("SELECT push_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && $user["push_token"]) {
        sendPushNotification($user["push_token"],
            "Consultation Confirmed! 🩺",
            "Your consultation with $doctorName has been confirmed. Check your dashboard.",
            ["type" => "consultation_confirmed"]
        );
    }
}