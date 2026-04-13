<?php
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

define("PAYSTACK_SECRET", getenv("PAYSTACK_SECRET") ?: "sk_test_00a4ff9e656f983dcf40323e2998cab270fba42e");
define("PAYSTACK_PUBLIC", getenv("PAYSTACK_PUBLIC") ?: "pk_test_9d6dafff401f0371b0f43171f1310da9b40be46f");

$method  = $_SERVER["REQUEST_METHOD"];
$action  = $_GET["action"] ?? "";
$decoded = verifyToken();
$userId  = $decoded["id"];

// Helper: call Paystack API
function paystackRequest($endpoint, $data = null, $method = "GET") {
    $url = "https://api.paystack.co" . $endpoint;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " . PAYSTACK_SECRET,
            "Content-Type: application/json",
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) throw new Exception("Paystack request failed: $err");
    return json_decode($response, true);
}

// INITIALIZE PAYMENT
if ($method === "POST" && $action === "initialize") {
    $data     = json_decode(file_get_contents("php://input"), true);
    $amount   = intval(floatval($data["amount"] ?? 0) * 100); // Convert GHS to pesewas
    $type     = $data["type"] ?? "order"; // order or consultation
    $ref_id   = $data["ref_id"] ?? null;  // order_id or consultation_id
    $email    = $data["email"] ?? "";

    if (!$amount || $amount <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid amount."]);
        exit;
    }
    if (!$email) {
        // Get email from user
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user  = $stmt->fetch();
        $email = $user["email"] ?? "";
    }

    $reference = "JDN-" . strtoupper($type) . "-" . $userId . "-" . time();

    $payload = [
        "email"     => $email,
        "amount"    => $amount,
        "currency"  => "GHS",
        "reference" => $reference,
        "metadata"  => [
            "user_id"  => $userId,
            "type"     => $type,
            "ref_id"   => $ref_id,
            "custom_fields" => [
                ["display_name" => "Payment Type", "variable_name" => "type",   "value" => $type],
                ["display_name" => "Reference ID", "variable_name" => "ref_id", "value" => $ref_id],
            ]
        ],
        "callback_url" => "https://jdnheath-api-production.up.railway.app/paystack.php?action=verify&reference=" . $reference,
    ];

    $result = paystackRequest("/transaction/initialize", $payload, "POST");

    if (!$result["status"]) {
        http_response_code(400);
        echo json_encode(["error" => $result["message"] ?? "Payment initialization failed."]);
        exit;
    }

    // Save payment record
    $pdo->prepare("INSERT INTO payments (user_id, reference, amount, type, ref_id, status) VALUES (?, ?, ?, ?, ?, 'pending')")
        ->execute([$userId, $reference, floatval($data["amount"]), $type, $ref_id]);

    echo json_encode([
        "status"            => true,
        "authorization_url" => $result["data"]["authorization_url"],
        "reference"         => $reference,
        "access_code"       => $result["data"]["access_code"],
    ]);
}

// VERIFY PAYMENT
elseif (($method === "GET" || $method === "POST") && $action === "verify") {
    $reference = $_GET["reference"] ?? (json_decode(file_get_contents("php://input"), true)["reference"] ?? "");

    if (!$reference) {
        http_response_code(400);
        echo json_encode(["error" => "Reference required."]);
        exit;
    }

    $result = paystackRequest("/transaction/verify/" . urlencode($reference));

    if (!$result["status"]) {
        http_response_code(400);
        echo json_encode(["error" => "Verification failed: " . ($result["message"] ?? "Unknown error")]);
        exit;
    }

    $txData = $result["data"];
    $status = $txData["status"]; // success, failed, abandoned

    // Update payment record
    $pdo->prepare("UPDATE payments SET status = ?, paid_at = CURRENT_TIMESTAMP, channel = ?, currency = ? WHERE reference = ?")
        ->execute([$status, $txData["channel"] ?? null, $txData["currency"] ?? "GHS", $reference]);

    if ($status === "success") {
        $meta   = $txData["metadata"] ?? [];
        $type   = $meta["type"] ?? null;
        $ref_id = $meta["ref_id"] ?? null;

        // Update order or consultation status
        if ($type === "order" && $ref_id) {
            $pdo->prepare("UPDATE orders SET status = 'confirmed', payment_status = 'paid' WHERE id = ? AND user_id = ?")
                ->execute([$ref_id, $userId]);
        } elseif ($type === "consultation" && $ref_id) {
            $pdo->prepare("UPDATE consultations SET status = 'confirmed', payment_status = 'paid' WHERE id = ? AND patient_id = ?")
                ->execute([$ref_id, $userId]);
        }

        echo json_encode([
            "status"    => true,
            "message"   => "Payment successful!",
            "amount"    => $txData["amount"] / 100,
            "currency"  => $txData["currency"],
            "channel"   => $txData["channel"],
            "reference" => $reference,
            "type"      => $type,
            "ref_id"    => $ref_id,
        ]);
    } else {
        echo json_encode([
            "status"    => false,
            "message"   => "Payment " . $status,
            "reference" => $reference,
        ]);
    }
}

// GET PAYMENT HISTORY
elseif ($method === "GET" && $action === "history") {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    echo json_encode(["payments" => $stmt->fetchAll()]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}