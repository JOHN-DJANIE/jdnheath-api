<?php
error_reporting(0);
ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$category = $_GET["category"] ?? "";
$search = $_GET["search"] ?? "";

if ($method === "GET") {
    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];
    if ($category && $category !== "all") {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    if ($search) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["products" => $stmt->fetchAll()]);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed."]);
}
