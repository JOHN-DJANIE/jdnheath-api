<?php
$file = __DIR__ . "/admin_auth.php";
$content = file_get_contents($file);

// Check if claims already exists
if (strpos($content, 'action === "claims"') !== false) {
    echo json_encode(["message" => "Claims already exists"]);
    exit;
}

$newEndpoints = '
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
';

// Insert before the closing else { 404 }
$content = str_replace(
    "  else {\n      http_response_code(404);\n      echo json_encode([\"error\" => \"Route not found.\"]);\n  }",
    $newEndpoints . "\n  else {\n      http_response_code(404);\n      echo json_encode([\"error\" => \"Route not found.\"]);\n  }",
    $content
);

file_put_contents($file, $content);
echo json_encode(["message" => "Patched!", "length" => strlen($content)]);