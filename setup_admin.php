<?php
require_once "db.php";

$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT 'admin',
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
    id SERIAL PRIMARY KEY,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER,
    action TEXT NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS insurance_claims (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    nhia_number TEXT NOT NULL,
    claim_type TEXT NOT NULL,
    amount REAL,
    consultation_id INTEGER,
    order_id INTEGER,
    status TEXT 'pending',
    notes TEXT,
    processed_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS nhia_members (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    nhia_number TEXT UNIQUE NOT NULL,
    membership_type TEXT 'standard',
    status TEXT 'active',
    expiry_date TEXT,
    verified INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$existing = $pdo->query("SELECT id FROM admins WHERE email = 'admin@jdnhealth.com'")->fetch();
if (!$existing) {
    $password = password_hash("Admin1234", PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?,?,?,?)")
        ->execute(["JDNHealth Admin", "admin@jdnhealth.com", $password, "superadmin"]);
}

$settings = [
    ["platform_name", "JDNHealth GH"],
    ["platform_email", "admin@jdnhealth.com"],
    ["sms_enabled", "1"],
    ["registration_enabled", "1"],
    ["maintenance_mode", "0"],
    ["nhia_integration", "1"],
    ["commission_rate", "10"],
];
$stmt = $pdo->prepare("INSERT OR IGNORE INTO platform_settings (key, value) VALUES (?,?)");
foreach ($settings as $s) { $stmt->execute($s); }

echo json_encode([
    "message" => "Admin setup complete!",
    "admin_email" => "admin@jdnhealth.com",
    "admin_password" => "Admin1234"
]);
