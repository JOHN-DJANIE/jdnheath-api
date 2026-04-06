<?php
require_once "db.php";

// Add new columns to users table
$columns = [
    "ALTER TABLE users ADD COLUMN nhia_number TEXT",
    "ALTER TABLE users ADD COLUMN profile_photo TEXT",
    "ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'patient'",
    "ALTER TABLE users ADD COLUMN is_verified INTEGER DEFAULT 0",
    "ALTER TABLE users ADD COLUMN verification_code TEXT",
    "ALTER TABLE users ADD COLUMN verification_expires INTEGER",
    "ALTER TABLE users ADD COLUMN reset_code TEXT",
    "ALTER TABLE users ADD COLUMN reset_expires INTEGER",
    "ALTER TABLE users ADD COLUMN two_fa_enabled INTEGER DEFAULT 0",
    "ALTER TABLE users ADD COLUMN two_fa_code TEXT",
    "ALTER TABLE users ADD COLUMN two_fa_expires INTEGER",
    "ALTER TABLE users ADD COLUMN last_login DATETIME",
    "ALTER TABLE users ADD COLUMN login_attempts INTEGER DEFAULT 0",
    "ALTER TABLE users ADD COLUMN locked_until INTEGER",
    "ALTER TABLE users ADD COLUMN date_of_birth TEXT",
    "ALTER TABLE users ADD COLUMN address TEXT",
    "ALTER TABLE users ADD COLUMN emergency_contact TEXT",
    "ALTER TABLE users ADD COLUMN blood_type TEXT",
    "ALTER TABLE users ADD COLUMN allergies TEXT",
];

foreach ($columns as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) { /* column exists */ }
}

// Create audit log table
$pdo->exec("CREATE TABLE IF NOT EXISTS auth_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    status TEXT DEFAULT 'success',
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Create sessions table
$pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL,
    ip_address TEXT,
    device TEXT,
    expires_at INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

echo json_encode(["message" => "Auth upgrade complete!"]);
