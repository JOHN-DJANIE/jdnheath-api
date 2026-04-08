<?php
require_once "db.php";

$pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    price REAL NOT NULL,
    unit TEXT,
    rx INTEGER DEFAULT 0,
    stock TEXT 'in',
    rating REAL DEFAULT 0,
    reviews INTEGER DEFAULT 0,
    description TEXT,
    manufacturer TEXT
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    total REAL NOT NULL,
    status TEXT 'processing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    price REAL NOT NULL
)");

// Seed products
$count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
if ($count == 0) {
    $stmt = $pdo->prepare("INSERT INTO products (name, category, price, unit, rx, stock, rating, reviews, description, manufacturer) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $products = [
        ["Amoxicillin 500mg", "antibiotic", 28, "pack of 21", 1, "in", 4.8, 142, "Broad-spectrum penicillin antibiotic for bacterial infections.", "Kinapharma Ltd"],
        ["Paracetamol 1000mg", "analgesic", 12.5, "pack of 24", 0, "in", 4.9, 891, "Effective pain and fever relief for adults.", "Ernest Chemists"],
        ["Metformin 850mg", "chronic", 45, "pack of 30", 1, "in", 4.7, 204, "First-line treatment for type 2 diabetes mellitus.", "Tobinco Pharma"],
        ["Amlodipine 5mg", "chronic", 38, "pack of 30", 1, "in", 4.6, 178, "Calcium channel blocker for hypertension and angina.", "Danadams Pharma"],
        ["Azithromycin 250mg", "antibiotic", 55, "pack of 6", 1, "low", 4.7, 93, "Macrolide antibiotic for respiratory and skin infections.", "Kinapharma Ltd"],
        ["Vitamin C 1000mg", "vitamin", 22, "pack of 30", 0, "in", 4.8, 512, "High-dose ascorbic acid for immune support.", "Tobinco Pharma"],
        ["Folic Acid 5mg", "maternal", 18, "pack of 30", 0, "in", 4.9, 327, "Essential supplement for pregnancy and neural tube prevention.", "Ernest Chemists"],
        ["Zinc Sulphate 20mg", "vitamin", 15, "pack of 30", 0, "in", 4.6, 198, "Immune support and wound healing supplement.", "Kinapharma Ltd"],
        ["Artemether 20mg", "antiviral", 42, "pack of 24", 1, "in", 4.8, 267, "Artemisinin-based antimalarial combination therapy.", "Danadams Pharma"],
        ["Ibuprofen 400mg", "analgesic", 16, "pack of 24", 0, "in", 4.7, 445, "Anti-inflammatory pain relief for headaches and muscle pain.", "Ernest Chemists"],
        ["Lisinopril 10mg", "chronic", 35, "pack of 30", 1, "in", 4.5, 156, "ACE inhibitor for high blood pressure and heart failure.", "Tobinco Pharma"],
        ["Vitamin D3 1000IU", "vitamin", 25, "pack of 30", 0, "in", 4.8, 389, "Essential vitamin for bone health and immune function.", "Kinapharma Ltd"],
    ];
    foreach ($products as $p) {
        $stmt->execute($p);
    }
    echo json_encode(["message" => "Pharmacy setup complete! " . count($products) . " products added."]);
} else {
    echo json_encode(["message" => "Products already seeded. Total: $count"]);
}
