<?php
error_reporting(0);
ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";
require_once "sms.php";

$method = $_SERVER["REQUEST_METHOD"];
$decoded = verifyToken();
$userId = $decoded["id"];

if ($method === "GET") {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(["orders" => $stmt->fetchAll()]);
}
// UPLOAD PRESCRIPTION
elseif ($method === "POST" && isset($_GET["action"]) && $_GET["action"] === "prescription") {
    if (!isset($_FILES["prescription"])) {
        http_response_code(400);
        echo json_encode(["error" => "No file uploaded."]);
        exit;
    }
    $file = $_FILES["prescription"];
    $allowed = ["image/jpeg","image/png","image/jpg","application/pdf"];
    if (!in_array($file["type"], $allowed)) {
        http_response_code(400);
        echo json_encode(["error" => "Only JPG, PNG or PDF files are allowed."]);
        exit;
    }
    if ($file["size"] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(["error" => "File too large. Maximum 5MB."]);
        exit;
    }
    try {
        // Virus scan before upload
        require_once "virusscan.php";
        $scanResult = scanFileForVirus($file["tmp_name"], $file["name"]);

        if (!$scanResult["clean"]) {
            http_response_code(422);
            echo json_encode([
                "error"      => "File rejected: virus or malware detected.",
                "malicious"  => $scanResult["malicious"],
                "suspicious" => $scanResult["suspicious"],
            ]);
            exit;
        }

        // Upload to Cloudinary only if clean
        require_once "cloudinary.php";
        $publicId = "rx_" . $userId . "_" . time();
        $url      = uploadToCloudinary($file["tmp_name"], "jdnhealth/prescriptions", $publicId);
        $filename = basename($url);

        echo json_encode([
            "success"   => true,
            "url"       => $url,
            "filename"  => $filename,
            "scan"      => [
                "clean"   => $scanResult["clean"],
                "engines" => $scanResult["total"],
                "status"  => $scanResult["status"],
                "warning" => $scanResult["warning"] ?? null,
            ],
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Upload failed: " . $e->getMessage()]);
    }
    exit;
}

elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $items = $data["items"] ?? [];
    $total = $data["total"] ?? 0;
    if (empty($items)) { http_response_code(400); echo json_encode(["error" => "No items in order."]); exit; }
    $prescription_url = $data["prescription_url"] ?? null;
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status, prescription_url) VALUES (?, ?, 'processing', ?)");
    $stmt->execute([$userId, $total, $prescription_url]);
    $orderId = $pdo->lastInsertId();
    // Send push notification
    require_once "push.php";
    notifyOrderPlaced($pdo, $userId, number_format($total, 2));
    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $itemStmt->execute([$orderId, $item["id"], $item["name"], $item["quantity"], $item["price"]]);
    }
    
    // Send SMS confirmation
    $userStmt = $pdo->prepare("SELECT phone, name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch();
    if ($userInfo["phone"]) {
        sendOrderConfirmSMS($userInfo["phone"], $orderId, number_format($total, 2));
    }
    echo json_encode(["message" => "Order placed successfully!", "order_id" => $orderId]);
}
else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}

