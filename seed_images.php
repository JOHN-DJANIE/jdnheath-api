<?php
require_once "db.php";

// Add image column if not exists
try { $pdo->exec("ALTER TABLE products ADD COLUMN image_url TEXT"); } catch (Exception $e) {}

// Update each product with a relevant Unsplash/Pexels image
$images = [
    ["Amoxicillin 500mg", "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&q=80"],
    ["Paracetamol 500mg", "https://images.unsplash.com/photo-1626716493137-b67fe9501e76?w=400&q=80"],
    ["Metformin 500mg", "https://images.unsplash.com/photo-1550572017-edd951b55104?w=400&q=80"],
    ["Vitamin C 1000mg", "https://images.unsplash.com/photo-1556909114-44e3e9399a2b?w=400&q=80"],
    ["Folic Acid 5mg", "https://images.unsplash.com/photo-1607619056574-7b8d3ee536b2?w=400&q=80"],
    ["Azithromycin 250mg", "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&q=80"],
    ["Ibuprofen 400mg", "https://images.unsplash.com/photo-1626716493137-b67fe9501e76?w=400&q=80"],
    ["Amlodipine 5mg", "https://images.unsplash.com/photo-1550572017-edd951b55104?w=400&q=80"],
    ["Vitamin D3 1000IU", "https://images.unsplash.com/photo-1556909114-44e3e9399a2b?w=400&q=80"],
    ["Ferrous Sulphate", "https://images.unsplash.com/photo-1607619056574-7b8d3ee536b2?w=400&q=80"],
    ["Acyclovir 400mg", "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&q=80"],
    ["Omeprazole 20mg", "https://images.unsplash.com/photo-1550572017-edd951b55104?w=400&q=80"],
];

// Generic fallback images by category
$categoryImages = [
    "antibiotic" => "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&q=80",
    "analgesic"  => "https://images.unsplash.com/photo-1626716493137-b67fe9501e76?w=400&q=80",
    "chronic"    => "https://images.unsplash.com/photo-1550572017-edd951b55104?w=400&q=80",
    "vitamin"    => "https://images.unsplash.com/photo-1556909114-44e3e9399a2b?w=400&q=80",
    "maternal"   => "https://images.unsplash.com/photo-1607619056574-7b8d3ee536b2?w=400&q=80",
    "antiviral"  => "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&q=80",
    "other"      => "https://images.unsplash.com/photo-1550572017-edd951b55104?w=400&q=80",
];

// Update by name first
foreach ($images as [$name, $url]) {
    $pdo->prepare("UPDATE products SET image_url = ? WHERE name LIKE ?")->execute([$url, "%$name%"]);
}

// Fill any remaining with category-based images
$products = $pdo->query("SELECT id, category FROM products WHERE image_url IS NULL OR image_url = ''")->fetchAll();
foreach ($products as $p) {
    $url = $categoryImages[$p["category"]] ?? $categoryImages["other"];
    $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$url, $p["id"]]);
}

$updated = $pdo->query("SELECT id, name, category, image_url FROM products")->fetchAll();
echo json_encode(["message" => "Images updated!", "products" => $updated], JSON_PRETTY_PRINT);
