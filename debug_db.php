<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

$host = "ep-proud-thunder-a471usl4-pooler.us-east-1.aws.neon.tech";
$connStr = "host=$host port=5432 dbname=neondb user=neondb_owner password=npg_wYIl1tr8KTWg sslmode=require options='endpoint=ep-proud-thunder-a471usl4'";

ob_start();
$conn = pg_connect($connStr);
$output = ob_get_clean();

echo "Output: $output\n";
echo "Conn: " . ($conn ? "OK" : "FAILED") . "\n";

if (!$conn) {
    // Try without options
    $connStr2 = "host=$host port=5432 dbname=neondb user=neondb_owner password=npg_wYIl1tr8KTWg sslmode=require";
    $conn2 = pg_connect($connStr2);
    echo "Without options: " . ($conn2 ? "OK" : "FAILED") . "\n";
    
    // Try with sslmode=disable
    $connStr3 = "host=$host port=5432 dbname=neondb user=neondb_owner password=npg_wYIl1tr8KTWg";
    $conn3 = pg_connect($connStr3);
    echo "Without SSL: " . ($conn3 ? "OK" : "FAILED") . "\n";
}