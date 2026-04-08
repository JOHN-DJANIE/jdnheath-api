<?php
require_once "db.php";

// Create migrations tracking table
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) UNIQUE NOT NULL,
    ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

function migration_ran($pdo, $name) {
    $stmt = $pdo->prepare("SELECT id FROM migrations WHERE migration = ?");
    $stmt->execute([$name]);
    return $stmt->fetch() !== false;
}

function run_migration($pdo, $name, $sql) {
    if (migration_ran($pdo, $name)) {
        echo json_encode(["skipped" => $name]) . "\n";
        return;
    }
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$name]);
        echo json_encode(["ran" => $name]) . "\n";
    } catch (PDOException $e) {
        echo json_encode(["error" => $name, "message" => $e->getMessage()]) . "\n";
    }
}

$results = [];

// ── MIGRATION 001 ─────────────────────────────────────────────────────────
run_migration($pdo, "001_add_user_profile_fields",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo TEXT;
     ALTER TABLE users ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(255);
     ALTER TABLE users ADD COLUMN IF NOT EXISTS allergies TEXT;"
);

// ── MIGRATION 002 ─────────────────────────────────────────────────────────
run_migration($pdo, "002_add_appointment_reschedule_fields",
    "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS rescheduled_from TIMESTAMP;
     ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reschedule_reason TEXT;
     ALTER TABLE appointments ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP;
     ALTER TABLE appointments ADD COLUMN IF NOT EXISTS confirmed_at TIMESTAMP;"
);

// ── MIGRATION 003 ─────────────────────────────────────────────────────────
run_migration($pdo, "003_add_doctor_verification",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS license_number VARCHAR(100);
     ALTER TABLE doctors ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE;
     ALTER TABLE doctors ADD COLUMN IF NOT EXISTS verified_at TIMESTAMP;
     ALTER TABLE doctors ADD COLUMN IF NOT EXISTS verification_status VARCHAR(20) DEFAULT 'pending';"
);

// ── MIGRATION 004 ─────────────────────────────────────────────────────────
run_migration($pdo, "004_add_order_delivery_tracking",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(100);
     ALTER TABLE orders ADD COLUMN IF NOT EXISTS estimated_delivery DATE;
     ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP;
     ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_notes TEXT;"
);

// ── MIGRATION 005 ─────────────────────────────────────────────────────────
run_migration($pdo, "005_add_vitals_notes",
    "ALTER TABLE vitals ADD COLUMN IF NOT EXISTS notes TEXT;
     ALTER TABLE vitals ADD COLUMN IF NOT EXISTS recorded_by VARCHAR(50) DEFAULT 'patient';"
);

// ── MIGRATION 006 ─────────────────────────────────────────────────────────
run_migration($pdo, "006_add_nhia_coverage_details",
    "ALTER TABLE nhia_members ADD COLUMN IF NOT EXISTS scheme_type VARCHAR(50) DEFAULT 'NHIS';
     ALTER TABLE nhia_members ADD COLUMN IF NOT EXISTS dependants INTEGER DEFAULT 0;
     ALTER TABLE nhia_members ADD COLUMN IF NOT EXISTS last_verified TIMESTAMP;"
);

// ── MIGRATION 007 ─────────────────────────────────────────────────────────
run_migration($pdo, "007_add_hospital_coordinates",
    "ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7);
     ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7);
     ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS website VARCHAR(255);
     ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS emergency_number VARCHAR(20);"
);

// ── MIGRATION 008 ─────────────────────────────────────────────────────────
run_migration($pdo, "008_add_product_inventory",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_quantity INTEGER DEFAULT 0;
     ALTER TABLE products ADD COLUMN IF NOT EXISTS reorder_level INTEGER DEFAULT 10;
     ALTER TABLE products ADD COLUMN IF NOT EXISTS expiry_date DATE;
     ALTER TABLE products ADD COLUMN IF NOT EXISTS batch_number VARCHAR(100);"
);

echo json_encode([
    "status" => "Migrations complete!",
    "ran_at" => date("Y-m-d H:i:s")
]);