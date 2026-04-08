<?php
require_once "db.php";

$tables = [
"CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    gender VARCHAR(10),
    blood_type VARCHAR(5),
    date_of_birth DATE,
    address TEXT,
    nhia_number VARCHAR(50),
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS doctors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    specialty VARCHAR(255),
    hospital VARCHAR(255),
    bio TEXT,
    avatar VARCHAR(10),
    price DECIMAL(10,2) DEFAULT 0,
    rating DECIMAL(3,1) DEFAULT 4.5,
    reviews INTEGER DEFAULT 0,
    conditions TEXT,
    availability VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS appointments (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    doctor_name VARCHAR(255),
    specialty VARCHAR(255),
    appointment_date DATE,
    appointment_time TIME,
    status VARCHAR(20) DEFAULT 'scheduled',
    notes TEXT,
    consultation_type VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS consultations (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    doctor_id INTEGER REFERENCES doctors(id),
    hospital_id INTEGER,
    consultation_type VARCHAR(20),
    appointment_date DATE,
    appointment_time TIME,
    symptoms TEXT,
    notes TEXT,
    total_price DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS vitals (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    blood_pressure_systolic INTEGER,
    blood_pressure_diastolic INTEGER,
    heart_rate INTEGER,
    temperature DECIMAL(4,1),
    weight DECIMAL(5,1),
    height DECIMAL(5,1),
    bmi DECIMAL(4,1),
    blood_sugar DECIMAL(5,2),
    oxygen_saturation INTEGER,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS medications (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    start_date DATE,
    end_date DATE,
    prescribed_by VARCHAR(255),
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS lab_results (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    test_name VARCHAR(255),
    result VARCHAR(255),
    unit VARCHAR(50),
    normal_range VARCHAR(100),
    status VARCHAR(20),
    lab_name VARCHAR(255),
    test_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10,2),
    stock VARCHAR(20) DEFAULT 'in_stock',
    rx_required BOOLEAN DEFAULT FALSE,
    image_url TEXT,
    manufacturer VARCHAR(255),
    unit VARCHAR(50),
    rating DECIMAL(3,1) DEFAULT 4.0,
    reviews INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    total DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'pending',
    prescription_url TEXT,
    delivery_address TEXT,
    payment_method VARCHAR(20),
    payment_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id),
    product_id INTEGER REFERENCES products(id),
    product_name VARCHAR(255),
    quantity INTEGER,
    price DECIMAL(10,2)
)",
"CREATE TABLE IF NOT EXISTS hospitals (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100),
    location VARCHAR(255),
    region VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(255),
    rating DECIMAL(3,1) DEFAULT 4.0,
    reviews INTEGER DEFAULT 0,
    departments TEXT,
    facilities TEXT,
    opening_hours VARCHAR(100) DEFAULT '24/7',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS nhia_members (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) UNIQUE,
    nhia_number VARCHAR(50) UNIQUE NOT NULL,
    membership_type VARCHAR(50) DEFAULT 'Basic',
    expiry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS nhia_claims (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    nhia_number VARCHAR(50),
    service_type VARCHAR(100),
    amount DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    notes TEXT
)",
"CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS doctor_availability (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER REFERENCES doctors(id),
    day_of_week VARCHAR(10),
    start_time TIME,
    end_time TIME,
    is_available BOOLEAN DEFAULT TRUE
)",
"CREATE TABLE IF NOT EXISTS earnings (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER REFERENCES doctors(id),
    consultation_id INTEGER,
    amount DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'pending',
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS sms_logs (
    id SERIAL PRIMARY KEY,
    recipient VARCHAR(20),
    message TEXT,
    status VARCHAR(20),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS records (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES users(id),
    title VARCHAR(255),
    description TEXT,
    file_url TEXT,
    record_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)"
];

$success = 0;
$errors = [];
foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        $success++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

echo json_encode([
    "message" => "PostgreSQL setup complete!",
    "tables_created" => $success,
    "errors" => $errors
]);