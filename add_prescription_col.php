<?php
require_once "db.php";
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN prescription_url TEXT");
    echo "Column added!";
} catch(Exception $e) {
    echo "Already exists or error: " . $e->getMessage();
}