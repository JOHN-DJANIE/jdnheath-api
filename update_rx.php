<?php
require_once "db.php";

// Amlodipine, Lisinopril, Metformin - common chronic care, remove Rx
$pdo->prepare("UPDATE products SET rx_required = FALSE WHERE id IN (4, 11, 3)")->execute([]);

// Keep Rx for: Amoxicillin(1), Azithromycin(5), Artemether(9)
$stmt = $pdo->prepare("SELECT id, name, rx_required FROM products ORDER BY id");
$stmt->execute([]);
echo json_encode(["updated" => true, "products" => $stmt->fetchAll()]);