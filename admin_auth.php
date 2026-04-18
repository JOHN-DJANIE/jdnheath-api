<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";
require_once "ratelimit.php";

require_once "admin_extra.php";
$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

function verifyAdmin($pdo) {
    $headers = getallheaders();
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";
    if (!$auth || !str_starts_with($auth, "Bearer ")) { http_response_code(401); echo json_encode(["error" => "No token."]); exit; }
    $token = substr($auth, 7);
    try {
        $decoded = (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key("jdnhealth_gh_super_secret_key_for_jwt_authentication_2026_secure", "HS256"));
        if ($decoded["role"] !== "admin" && $decoded["role"] !== "superadmin") { http_response_code(403); echo json_encode(["error" => "Access denied."]); exit; }
        return $decoded;
    } catch (Exception $e) { http_response_code(403); echo json_encode(["error" => "Invalid token."]); exit; }
}

if ($method === "POST" && $action === "login") {
    try { checkRateLimit($pdo, "login", $RATE_LIMITS); } catch (Exception $e) { /* rate_limits table may not exist */ }
    $data = json_decode(file_get_contents("php://input"), true);
    $email = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";
    if (!$email || !$password) { http_response_code(400); echo json_encode(["error" => "Email and password required."]); exit; }
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin["password"])) { http_response_code(401); echo json_encode(["error" => "Invalid email or password."]); exit; }
    $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$admin["id"]]);
    $token = generateToken(["id" => $admin["id"], "email" => $admin["email"], "role" => $admin["role"]]);
    echo json_encode(["message" => "Login successful.", "token" => $token, "admin" => ["id" => $admin["id"], "name" => $admin["name"], "email" => $admin["email"], "role" => $admin["role"]]]);
}
elseif ($method === "GET" && $action === "stats") {
    verifyAdmin($pdo);
    $uq = $pdo->prepare("SELECT COUNT(*) as count FROM users"); $uq->execute(); $users = $uq->fetch()["count"];
    $dq = $pdo->prepare("SELECT COUNT(*) as count FROM doctors WHERE is_active = true"); $dq->execute(); $doctors = $dq->fetch()["count"];
    $hq = $pdo->prepare("SELECT COUNT(*) as count FROM hospitals WHERE is_active = true"); $hq->execute(); $hospitals = $hq->fetch()["count"];
    $cq = $pdo->prepare("SELECT (SELECT COUNT(*) FROM consultations) + (SELECT COUNT(*) FROM appointments) as count"); $cq->execute(); $consultations = $cq->fetch()["count"];
    $oq = $pdo->prepare("SELECT COUNT(*) as count FROM orders"); $oq->execute(); $orders = $oq->fetch()["count"];
    $rq = $pdo->prepare("SELECT SUM(total) as total FROM orders"); $rq->execute(); $revenue = $rq->fetch()["total"] ?? 0;
    $cr = $pdo->prepare("SELECT SUM(total_price) as total FROM consultations WHERE status = ? AND patient_id IS NOT NULL"); $cr->execute(["completed"]); $consult_revenue = $cr->fetch()["total"] ?? 0;
    $nq = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE created_at::date = CURRENT_DATE"); $nq->execute(); $new_users_today = $nq->fetch()["count"];
    $pc = $pdo->prepare("SELECT COUNT(*) as count FROM consultations WHERE status = ?"); $pc->execute(["pending"]); $pending_consults = $pc->fetch()["count"];
    echo json_encode(["users" => $users, "doctors" => $doctors, "hospitals" => $hospitals, "consultations" => $consultations, "orders" => $orders, "pharmacy_revenue" => $revenue, "consult_revenue" => $consult_revenue, "new_users_today" => $new_users_today, "pending_consults" => $pending_consults, "total_revenue" => $revenue + $consult_revenue]);
}
elseif ($method === "GET" && $action === "users") {
    verifyAdmin($pdo);
    $search = $_GET["search"] ?? "";
    $sql = "SELECT id, name, email, phone, role, is_verified, nhia_number, blood_type, created_at FROM users";
    $params = [];
    if ($search) { $sql .= " WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?"; $params = ["%$search%", "%$search%", "%$search%"]; }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    echo json_encode(["users" => $stmt->fetchAll()]);
}
elseif ($method === "DELETE" && $action === "user") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    echo json_encode(["message" => "User deleted."]);
}
elseif ($method === "GET" && $action === "doctors") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT id, name, email, specialty, hospital, rating, consultation_fee, is_verified, is_active, total_consultations, total_earnings FROM doctors ORDER BY name"); $stmt->execute();
    echo json_encode(["doctors" => $stmt->fetchAll()]);
}
elseif ($method === "PUT" && $action === "doctor") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE doctors SET is_verified = ?, is_active = ? WHERE id = ?")->execute([$data["is_verified"] ?? true, $data["is_active"] ?? true, $id]);
    echo json_encode(["message" => "Doctor updated."]);
}
elseif ($method === "GET" && $action === "hospitals") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT * FROM hospitals ORDER BY name"); $stmt->execute();
    echo json_encode(["hospitals" => $stmt->fetchAll()]);
}
elseif ($method === "GET" && $action === "products") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT * FROM products ORDER BY name"); $stmt->execute();
    echo json_encode(["products" => $stmt->fetchAll()]);
}
elseif ($method === "POST" && $action === "product") {
    verifyAdmin($pdo);
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("INSERT INTO products (name, category, price, unit, rx, stock, description, manufacturer) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$data["name"], $data["category"], $data["price"], $data["unit"] ?? null, $data["rx"] ?? 0, $data["stock"] ?? "in", $data["description"] ?? null, $data["manufacturer"] ?? null]);
    echo json_encode(["message" => "Product added.", "id" => $pdo->lastInsertId()]);
}
elseif ($method === "PUT" && $action === "product") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE products SET name=?, category=?, price=?, unit=?, rx=?, stock=?, description=?, manufacturer=? WHERE id=?")->execute([$data["name"], $data["category"], $data["price"], $data["unit"], $data["rx"] ?? 0, $data["stock"], $data["description"], $data["manufacturer"], $id]);
    echo json_encode(["message" => "Product updated."]);
}
elseif ($method === "DELETE" && $action === "product") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    echo json_encode(["message" => "Product deleted."]);
}
elseif ($method === "GET" && $action === "orders") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT o.*, u.name as patient_name, u.email as patient_email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC"); $stmt->execute();
    echo json_encode(["orders" => $stmt->fetchAll()]);
}
elseif ($method === "PUT" && $action === "order") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$data["status"], $id]);
    echo json_encode(["message" => "Order updated."]);
}
elseif ($method === "GET" && $action === "consultations") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT id, patient_id, NULL as doctor_id, 'appointment' as consultation_type, appointment_date, appointment_time, status, 0 as total_price, created_at, (SELECT name FROM users WHERE id = patient_id) as patient_name, doctor_name FROM appointments UNION ALL SELECT id, patient_id, doctor_id, consultation_type, appointment_date, appointment_time, status, total_price, created_at, (SELECT name FROM users WHERE id = patient_id) as patient_name, (SELECT name FROM doctors WHERE id = doctor_id) as doctor_name FROM consultations ORDER BY created_at DESC"); $stmt->execute();
    echo json_encode(["consultations" => $stmt->fetchAll()]);
}
elseif ($method === "GET" && $action === "analytics") {
    verifyAdmin($pdo);
    $td = $pdo->prepare("SELECT name, specialty, total_consultations, total_earnings FROM doctors ORDER BY total_consultations DESC LIMIT 5"); $td->execute(); $top_doctors = $td->fetchAll();
    $tp = $pdo->prepare("SELECT name, 0 as orders FROM products ORDER BY name LIMIT 5"); $tp->execute(); $top_products = $tp->fetchAll();
    echo json_encode(["top_doctors" => $top_doctors, "top_products" => $top_products]);
}
elseif ($method === "GET" && $action === "settings") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT key, value FROM platform_settings"); $stmt->execute();
    $settings = [];
    foreach ($stmt->fetchAll() as $row) { $settings[$row["key"]] = $row["value"]; }
    echo json_encode(["settings" => $settings]);
}
elseif ($method === "PUT" && $action === "settings") {
    verifyAdmin($pdo);
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO platform_settings (key, value) VALUES (?, ?)");
    foreach ($data as $key => $value) { $stmt->execute([$key, $value]); }
    echo json_encode(["message" => "Settings updated."]);
}
elseif ($method === "POST" && $action === "broadcast") {
    verifyAdmin($pdo);
    require_once "sms.php";
    $data = json_decode(file_get_contents("php://input"), true);
    $message = $data["message"] ?? "";
    $target = $data["target"] ?? "all";
    if (!$message) { http_response_code(400); echo json_encode(["error" => "Message required."]); exit; }
    $sql = "SELECT phone, name FROM users WHERE phone IS NOT NULL AND phone != ''";
    if ($target === "verified") $sql .= " AND is_verified = true";
    $uStmt = $pdo->prepare($sql); $uStmt->execute($params); $users = $uStmt->fetchAll();
    $sent = 0;
    foreach ($users as $user) { if (sendSMS($user["phone"], $message)) $sent++; }
    echo json_encode(["message" => "Broadcast sent.", "sent" => $sent, "total" => count($users)]);
}
// GET CLAIMS
elseif ($method === "GET" && $action === "claims") {
    verifyAdmin($pdo);
    $stmt = $pdo->prepare("SELECT ic.*, u.name as patient_name, u.email as patient_email FROM insurance_claims ic LEFT JOIN users u ON ic.user_id = u.id ORDER BY ic.created_at DESC");
    $stmt->execute([]);
    echo json_encode(["claims" => $stmt->fetchAll()]);
}
// UPDATE CLAIM
elseif ($method === "PUT" && $action === "claim") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Claim ID required"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE insurance_claims SET status = ?, notes = ? WHERE id = ?")->execute([$data["status"] ?? "pending", $data["notes"] ?? null, $id]);
    echo json_encode(["message" => "Claim updated."]);
}
// ADD HOSPITAL
elseif ($method === "POST" && $action === "hospital") {
    verifyAdmin($pdo);
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data["name"])) { http_response_code(400); echo json_encode(["error" => "Hospital name required"]); exit; }
    $stmt = $pdo->prepare("INSERT INTO hospitals (name, type, location, region, phone, email, departments, facilities, opening_hours, is_active) VALUES (?,?,?,?,?,?,?,?,?,TRUE)");
    $stmt->execute([$data["name"], $data["type"] ?? "Government Hospital", $data["location"] ?? "", $data["region"] ?? "Greater Accra", $data["phone"] ?? null, $data["email"] ?? null, $data["departments"] ?? null, $data["facilities"] ?? null, $data["opening_hours"] ?? "24/7"]);
    echo json_encode(["message" => "Hospital added.", "id" => $pdo->lastInsertId()]);
}
// UPDATE HOSPITAL
elseif ($method === "PUT" && $action === "hospital") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Hospital ID required"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE hospitals SET name=?, type=?, location=?, region=?, phone=?, email=?, departments=?, facilities=?, opening_hours=? WHERE id=?")->execute([$data["name"], $data["type"] ?? "Government Hospital", $data["location"] ?? "", $data["region"] ?? "Greater Accra", $data["phone"] ?? null, $data["email"] ?? null, $data["departments"] ?? null, $data["facilities"] ?? null, $data["opening_hours"] ?? "24/7", $id]);
    echo json_encode(["message" => "Hospital updated."]);
}
// DELETE HOSPITAL
elseif ($method === "DELETE" && $action === "hospital") {
    verifyAdmin($pdo);
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Hospital ID required"]); exit; }
    $pdo->prepare("DELETE FROM hospitals WHERE id = ?")->execute([$id]);
    echo json_encode(["message" => "Hospital deleted."]);
}
else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}

