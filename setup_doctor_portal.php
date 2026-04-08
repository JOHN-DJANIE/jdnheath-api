<?php
require_once "db.php";

// Add doctor account fields
$columns = [
    "ALTER TABLE doctors ADD COLUMN user_id INTEGER",
    "ALTER TABLE doctors ADD COLUMN email TEXT",
    "ALTER TABLE doctors ADD COLUMN phone TEXT",
    "ALTER TABLE doctors ADD COLUMN password TEXT",
    "ALTER TABLE doctors ADD COLUMN license_number TEXT",
    "ALTER TABLE doctors ADD COLUMN years_experience INTEGER DEFAULT 0",
    "ALTER TABLE doctors ADD COLUMN consultation_fee REAL DEFAULT 0",
    "ALTER TABLE doctors ADD COLUMN is_verified INTEGER DEFAULT 0",
    "ALTER TABLE doctors ADD COLUMN working_days TEXT DEFAULT 'Mon,Tue,Wed,Thu,Fri'",
    "ALTER TABLE doctors ADD COLUMN working_hours_start TEXT DEFAULT '08:00'",
    "ALTER TABLE doctors ADD COLUMN working_hours_end TEXT DEFAULT '17:00'",
    "ALTER TABLE doctors ADD COLUMN max_patients_per_day INTEGER DEFAULT 20",
    "ALTER TABLE doctors ADD COLUMN total_earnings REAL DEFAULT 0",
    "ALTER TABLE doctors ADD COLUMN total_consultations INTEGER DEFAULT 0",
];

foreach ($columns as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) { /* column exists */ }
}

// Create prescriptions table
$pdo->exec("CREATE TABLE IF NOT EXISTS prescriptions (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL,
    patient_id INTEGER NOT NULL,
    consultation_id INTEGER,
    diagnosis TEXT,
    medications TEXT,
    instructions TEXT,
    follow_up_date TEXT,
    status TEXT DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create doctor availability slots table
$pdo->exec("CREATE TABLE IF NOT EXISTS availability_slots (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    time_slot TEXT NOT NULL,
    is_booked INTEGER DEFAULT 0,
    consultation_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create doctor earnings table
$pdo->exec("CREATE TABLE IF NOT EXISTS doctor_earnings (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL,
    consultation_id INTEGER,
    amount REAL NOT NULL,
    type TEXT DEFAULT 'consultation',
    status TEXT DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Seed doctor accounts (link doctors to user accounts)
$password = password_hash("Doctor1234", PASSWORD_BCRYPT);
$doctors = $pdo->query("SELECT id, name, email FROM doctors WHERE email IS NULL OR email = ''")->fetchAll();

$stmt = $pdo->prepare("UPDATE doctors SET email = ?, password = ?, phone = ?, consultation_fee = ?, is_verified = 1 WHERE id = ?");
$doctorEmails = [
    1 => ["dr.ama.owusu@jdnhealth.com", "0244111001", 180],
    2 => ["dr.kweku.asante@jdnhealth.com", "0244111002", 150],
    3 => ["dr.efua.acheampong@jdnhealth.com", "0244111003", 160],
    4 => ["dr.yaw.darko@jdnhealth.com", "0244111004", 200],
    5 => ["dr.abena.mensah@jdnhealth.com", "0244111005", 220],
    6 => ["dr.kofi.boateng@jdnhealth.com", "0244111006", 250],
    7 => ["dr.akosua.frimpong@jdnhealth.com", "0244111007", 190],
    8 => ["dr.nana.adjei@jdnhealth.com", "0244111008", 170],
    9 => ["dr.kwame.osei@jdnhealth.com", "0244111009", 180],
    10 => ["dr.adwoa.asante@jdnhealth.com", "0244111010", 210],
];

foreach ($doctorEmails as $id => $info) {
    $stmt->execute([$info[0], $password, $info[1], $info[2], $id]);
}

echo json_encode(["message" => "Doctor portal setup complete!", "default_password" => "Doctor1234"]);
