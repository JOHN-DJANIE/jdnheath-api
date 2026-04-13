<?php
error_reporting(0);
ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";

$method   = $_SERVER["REQUEST_METHOD"];
$action   = $_GET["action"] ?? "";
$category = $_GET["category"] ?? "";
$search   = $_GET["search"] ?? "";

// Helper: auto-update stock text based on quantity
function updateStockStatus($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT stock_quantity, reorder_level FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $p = $stmt->fetch();
    if (!$p) return;
    $qty   = intval($p["stock_quantity"]);
    $level = intval($p["reorder_level"]);
    $status = $qty <= 0 ? "out" : ($qty <= $level ? "low" : "in");
    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$status, $productId]);
}

// GET all products
if ($method === "GET" && !$action) {
    $sql    = "SELECT *, CASE WHEN stock_quantity <= 0 THEN 'out' WHEN stock_quantity <= reorder_level THEN 'low' ELSE 'in' END AS stock_status FROM products WHERE deleted_at IS NULL AND is_active = true";
    $params = [];
    if ($category && $category !== "all") { $sql .= " AND category = ?"; $params[] = $category; }
    if ($search) { $sql .= " AND (name ILIKE ? OR description ILIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["products" => $stmt->fetchAll()]);
}

// GET low stock alert (admin)
elseif ($method === "GET" && $action === "low_stock") {
    $stmt = $pdo->prepare("SELECT id, name, stock_quantity, reorder_level, category FROM products WHERE deleted_at IS NULL AND stock_quantity <= reorder_level AND is_active = true ORDER BY stock_quantity ASC");
    $stmt->execute([]);
    $low = $stmt->fetchAll();
    echo json_encode(["low_stock" => $low, "count" => count($low)]);
}

// GET single product
elseif ($method === "GET" && $action === "single") {
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Product ID required."]); exit; }
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) { http_response_code(404); echo json_encode(["error" => "Product not found."]); exit; }
    echo json_encode(["product" => $product]);
}

// POST reduce stock when order is placed
elseif ($method === "POST" && $action === "reduce_stock") {
    $data  = json_decode(file_get_contents("php://input"), true);
    $items = $data["items"] ?? [];
    if (empty($items)) { http_response_code(400); echo json_encode(["error" => "No items provided."]); exit; }

    $errors  = [];
    $updated = [];

    foreach ($items as $item) {
        $productId = $item["product_id"] ?? null;
        $qty       = intval($item["quantity"] ?? 0);
        if (!$productId || $qty <= 0) continue;

        $stmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) { $errors[] = "Product ID $productId not found."; continue; }
        if (intval($product["stock_quantity"]) < $qty) {
            $errors[] = "{$product["name"]}: only {$product["stock_quantity"]} units available, requested $qty.";
            continue;
        }

        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$qty, $productId]);
        updateStockStatus($pdo, $productId);
        $updated[] = ["product_id" => $productId, "name" => $product["name"], "reduced_by" => $qty];
    }

    echo json_encode(["message" => "Stock updated.", "updated" => $updated, "errors" => $errors]);
}

// POST restock (admin)
elseif ($method === "POST" && $action === "restock") {
    $data      = json_decode(file_get_contents("php://input"), true);
    $productId = $data["product_id"] ?? null;
    $qty       = intval($data["quantity"] ?? 0);

    if (!$productId || $qty <= 0) { http_response_code(400); echo json_encode(["error" => "Product ID and quantity required."]); exit; }

    $stmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) { http_response_code(404); echo json_encode(["error" => "Product not found."]); exit; }

    $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$qty, $productId]);
    updateStockStatus($pdo, $productId);

    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $newQty = $stmt->fetch()["stock_quantity"];

    echo json_encode(["message" => "Restocked successfully.", "product" => $product["name"], "added" => $qty, "new_stock" => $newQty]);
}

// DELETE product (soft)
elseif ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    if (!$id) { http_response_code(400); echo json_encode(["error" => "Product ID required."]); exit; }
    $pdo->prepare("UPDATE products SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    echo json_encode(["message" => "Product deleted."]);
}

else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed."]);
}