<?php
require_once "cors.php";
require_once "db.php";
try {
    $stmt = $pdo->prepare("SELECT o.*, u.name as patient_name, u.email as patient_email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
    $stmt->execute([]);
    echo json_encode(["orders" => $stmt->fetchAll(), "count" => $stmt->rowCount()]);
} catch(Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
