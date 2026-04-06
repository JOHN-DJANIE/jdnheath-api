<?php
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

define("DAILY_API_KEY", "3d8b1f2d5abae45232f19b0f312c6dab7506a4536f4e04086053562ba28f6f35");
define("DAILY_BASE_URL", "https://api.daily.co/v1");

function flexVerifyToken() {
    $headers = getallheaders();
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";
    if (!$auth || !str_starts_with($auth, "Bearer ")) { http_response_code(401); echo json_encode(["error" => "No token provided."]); exit; }
    $token = substr($auth, 7);
    try {
        return (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key("jdnhealth_gh_super_secret_key_for_jwt_authentication_2026_secure", "HS256"));
    } catch (Exception $e) { http_response_code(403); echo json_encode(["error" => "Invalid token."]); exit; }
}

function dailyRequest($method, $endpoint, $data = null) {
    $ch = curl_init(DAILY_BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer " . DAILY_API_KEY]);
    if ($method === "POST") { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    elseif ($method === "DELETE") { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); }
    $result = curl_exec($ch); curl_close($ch);
    return json_decode($result, true);
}

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

if ($method === "POST" && $action === "create_room") {
    $decoded = flexVerifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $consultationId = $data["consultation_id"] ?? null;
    if (!$consultationId) { http_response_code(400); echo json_encode(["error" => "Consultation ID required."]); exit; }
    $stmt = $pdo->prepare("SELECT video_room_url, video_room_name FROM consultations WHERE id = ?");
    $stmt->execute([$consultationId]);
    $consult = $stmt->fetch();
    if ($consult["video_room_url"]) { echo json_encode(["room_url" => $consult["video_room_url"], "room_name" => $consult["video_room_name"], "existing" => true]); exit; }
    $roomName = "jdnhealth-" . $consultationId . "-" . time();
    $expiry = time() + (2 * 60 * 60);
    $room = dailyRequest("POST", "/rooms", ["name" => $roomName, "privacy" => "private", "properties" => ["exp" => $expiry, "max_participants" => 2, "enable_chat" => true, "enable_screenshare" => true]]);
    if (!isset($room["url"])) { http_response_code(500); echo json_encode(["error" => "Failed to create room.", "details" => $room]); exit; }
    $pdo->prepare("UPDATE consultations SET video_room_url = ?, video_room_name = ? WHERE id = ?")->execute([$room["url"], $roomName, $consultationId]);
    $stmt = $pdo->prepare("SELECT u.phone, u.name, d.name as doctor_name FROM consultations c JOIN users u ON c.user_id = u.id LEFT JOIN doctors d ON c.doctor_id = d.id WHERE c.id = ?");
    $stmt->execute([$consultationId]);
    $info = $stmt->fetch();
    echo json_encode(["room_url" => $room["url"], "room_name" => $roomName, "expires" => $expiry]);
}
elseif ($method === "POST" && $action === "get_token") {
    $decoded = flexVerifyToken();
    $data = json_decode(file_get_contents("php://input"), true);
    $roomName = $data["room_name"] ?? null;
    $userName = $data["user_name"] ?? "User";
    $isOwner = $data["is_owner"] ?? false;
    if (!$roomName) { http_response_code(400); echo json_encode(["error" => "Room name required."]); exit; }
    $token = dailyRequest("POST", "/meeting-tokens", ["properties" => ["room_name" => $roomName, "user_name" => $userName, "is_owner" => $isOwner, "exp" => time() + (2 * 60 * 60)]]);
    if (!isset($token["token"])) { http_response_code(500); echo json_encode(["error" => "Failed to create token."]); exit; }
    echo json_encode(["token" => $token["token"]]);
}
elseif ($method === "GET" && $action === "room") {
    $decoded = flexVerifyToken();
    $consultationId = $_GET["consultation_id"] ?? null;
    $stmt = $pdo->prepare("SELECT video_room_url, video_room_name FROM consultations WHERE id = ?");
    $stmt->execute([$consultationId]);
    $consult = $stmt->fetch();
    echo json_encode(["room_url" => $consult["video_room_url"] ?? null, "room_name" => $consult["video_room_name"] ?? null]);
}
elseif ($method === "DELETE" && $action === "room") {
    $decoded = flexVerifyToken();
    $roomName = $_GET["room_name"] ?? null;
    if ($roomName) { dailyRequest("DELETE", "/rooms/" . $roomName); }
    echo json_encode(["message" => "Room deleted."]);
}
else { http_response_code(404); echo json_encode(["error" => "Route not found."]); }
