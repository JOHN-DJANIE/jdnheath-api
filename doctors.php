<?php
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";
$id = $_GET["id"] ?? null;

if ($method === "GET" && $action === "one" && $id) {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["doctor" => $stmt->fetch()]);
} elseif ($method === "GET") {
    $specialty = $_GET["specialty"] ?? "";
    $search = $_GET["search"] ?? "";
    $sql = "SELECT * FROM doctors WHERE is_active = TRUE AND is_verified = 1 AND verification_status = 'approved'";
    $params = [];
    if ($specialty) { $sql .= " AND specialty LIKE ?"; $params[] = "%$specialty%"; }
    if ($search) { $sql .= " AND (name LIKE ? OR specialty LIKE ? OR conditions LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY rating DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["doctors" => $stmt->fetchAll()]);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed."]);
}
