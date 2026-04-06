<?php
error_reporting(0); ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$id = $_GET["id"] ?? null;

if ($method === "GET" && $id) {
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["hospital" => $stmt->fetch()]);
} elseif ($method === "GET") {
    $region = $_GET["region"] ?? "";
    $type = $_GET["type"] ?? "";
    $search = $_GET["search"] ?? "";
    $sql = "SELECT * FROM hospitals WHERE is_active = 1";
    $params = [];
    if ($region) { $sql .= " AND region = ?"; $params[] = $region; }
    if ($type) { $sql .= " AND type = ?"; $params[] = $type; }
    if ($search) { $sql .= " AND (name LIKE ? OR location LIKE ? OR departments LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY rating DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["hospitals" => $stmt->fetchAll()]);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed."]);
}
