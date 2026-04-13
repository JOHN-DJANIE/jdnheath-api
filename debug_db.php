<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "PHP version: " . PHP_VERSION . "\n";
echo "pgsql loaded: " . (extension_loaded("pgsql") ? "YES" : "NO") . "\n";
echo "DB_HOST: " . (getenv("DB_HOST") ?: "using fallback") . "\n";

$host = getenv("DB_HOST") ?: "ep-proud-thunder-a471usl4-pooler.us-east-1.aws.neon.tech";
$connStr = "host=$host port=5432 dbname=neondb user=neondb_owner password=npg_wYIl1tr8KTWg sslmode=require options='endpoint=ep-proud-thunder-a471usl4'";

$conn = @pg_connect($connStr);
if ($conn) {
    echo "DB connected OK!\n";
    $r = pg_query($conn, "SELECT count(*) FROM doctors");
    $row = pg_fetch_row($r);
    echo "Doctors count: " . $row[0] . "\n";
} else {
    echo "DB failed: " . pg_last_error() . "\n";
}