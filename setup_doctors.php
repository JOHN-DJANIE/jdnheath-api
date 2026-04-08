<?php
require_once "db.php";

// Add missing columns if they dont exist
$alterCols = [
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS location VARCHAR(255)",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS consultation_fee DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS experience INTEGER DEFAULT 0",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS languages VARCHAR(255)",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS education TEXT",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS certifications TEXT",
    "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS is_available BOOLEAN DEFAULT TRUE",
];
foreach ($alterCols as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) {}
}

$pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    specialty TEXT NOT NULL,
    hospital TEXT,
    location TEXT,
    rating REAL DEFAULT 0,
    reviews INTEGER DEFAULT 0,
    price REAL NOT NULL,
    availability TEXT DEFAULT 'Available Today',
    avatar TEXT,
    bio TEXT,
    conditions TEXT,
    is_active INTEGER DEFAULT 1
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS hospitals (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    type TEXT DEFAULT 'General',
    location TEXT NOT NULL,
    region TEXT,
    phone TEXT,
    email TEXT,
    rating REAL DEFAULT 0,
    reviews INTEGER DEFAULT 0,
    departments TEXT,
    facilities TEXT,
    opening_hours TEXT DEFAULT '24/7',
    is_active INTEGER DEFAULT 1
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS consultations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    doctor_id INTEGER,
    hospital_id INTEGER,
    consultation_type TEXT NOT NULL,
    appointment_date TEXT,
    appointment_time TEXT,
    symptoms TEXT,
    notes TEXT,
    status TEXT DEFAULT 'pending',
    total_price REAL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$docCount = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
if ($docCount == 0) {
    $stmt = $pdo->prepare("INSERT INTO doctors (name, specialty, hospital, location, rating, reviews, price, availability, avatar, bio, conditions) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT DO NOTHING");
    $doctors = [
        ["Dr. Ama Owusu", "Internal Medicine & Cardiology", "Korle Bu Teaching Hospital", "Accra", 4.9, 312, 180, "Available Today", "AO", "Specialist in cardiovascular diseases and internal medicine with over 15 years experience.", "chest pain,hypertension,fatigue,shortness of breath"],
        ["Dr. Kweku Asante", "Pediatrics & Child Health", "37 Military Hospital", "Accra", 4.8, 245, 150, "Available Today", "KA", "Dedicated pediatrician providing comprehensive child healthcare from newborns to teenagers.", "fever,cough,vomiting,child health"],
        ["Dr. Efua Acheampong", "General Medicine", "Komfo Anokye Teaching Hospital", "Kumasi", 4.9, 418, 160, "Next: Tomorrow", "EA", "General practitioner with expertise in preventive care and chronic disease management.", "headache,fever,general checkup,diabetes"],
        ["Dr. Yaw Darko", "Dermatology", "University of Ghana Medical Centre", "Accra", 4.7, 189, 200, "Available Now", "YD", "Board-certified dermatologist specializing in skin conditions, cosmetic procedures and hair disorders.", "skin rash,itching,skin infection,acne"],
        ["Dr. Abena Mensah", "Orthopedics", "Ridge Hospital", "Accra", 4.8, 276, 220, "Available Today", "AM", "Orthopedic surgeon with focus on sports injuries, joint replacement and spinal conditions.", "joint pain,back pain,muscle pain,fractures"],
        ["Dr. Kofi Boateng", "Neurology", "Korle Bu Teaching Hospital", "Accra", 4.7, 203, 250, "Next: Tomorrow", "KB", "Neurologist specializing in headaches, epilepsy, stroke and neurodegenerative diseases.", "headache,dizziness,seizures,stroke"],
        ["Dr. Akosua Frimpong", "Obstetrics & Gynecology", "Tema General Hospital", "Tema", 4.9, 521, 190, "Available Today", "AF", "OB/GYN specialist providing comprehensive women health services including prenatal care.", "pregnancy,women health,fertility,menstrual issues"],
        ["Dr. Nana Adjei", "Psychiatry & Mental Health", "Accra Psychiatric Hospital", "Accra", 4.6, 167, 170, "Available Today", "NA", "Psychiatrist helping patients manage mental health conditions with compassion and evidence-based care.", "anxiety,depression,stress,mental health"],
        ["Dr. Kwame Osei", "Ophthalmology", "Eye Centre Ghana", "Accra", 4.8, 234, 180, "Available Now", "KO", "Eye specialist providing diagnosis and treatment of all eye conditions including cataracts and glaucoma.", "eye pain,blurred vision,eye infection"],
        ["Dr. Adwoa Asante", "Endocrinology", "37 Military Hospital", "Accra", 4.7, 198, 210, "Next: Tomorrow", "AA", "Endocrinologist specializing in diabetes, thyroid disorders and hormonal imbalances.", "diabetes,thyroid,weight issues,hormonal problems"],
    ];
    foreach ($doctors as $d) { $stmt->execute($d); }
}

$hospCount = $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
if ($hospCount == 0) {
    $stmt = $pdo->prepare("INSERT INTO hospitals (name, type, location, region, phone, email, rating, reviews, departments, facilities, opening_hours) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT DO NOTHING");
    $hospitals = [
        ["Korle Bu Teaching Hospital", "Teaching Hospital", "Guggisberg Ave, Accra", "Greater Accra", "+233 302 674 418", "info@kbth.gov.gh", 4.6, 1842, "Cardiology,Neurology,Oncology,Pediatrics,Surgery,Emergency,Radiology", "ICU,MRI,CT Scan,Laboratory,Pharmacy,Ambulance", "24/7"],
        ["Komfo Anokye Teaching Hospital", "Teaching Hospital", "Okomfo Anokye Rd, Kumasi", "Ashanti", "+233 322 022 301", "info@kath.gov.gh", 4.5, 1523, "General Medicine,Surgery,Pediatrics,Maternity,Orthopedics,Psychiatry", "ICU,Laboratory,Blood Bank,Pharmacy,Ambulance", "24/7"],
        ["37 Military Hospital", "Military Hospital", "Liberation Rd, Accra", "Greater Accra", "+233 302 776 111", "info@37mh.gov.gh", 4.7, 987, "Internal Medicine,Surgery,Pediatrics,Cardiology,Radiology,Emergency", "ICU,MRI,CT Scan,Laboratory,Pharmacy", "24/7"],
        ["University of Ghana Medical Centre", "University Hospital", "University of Ghana, Legon", "Greater Accra", "+233 302 213 820", "info@ugmc.edu.gh", 4.8, 756, "General Medicine,Surgery,Pediatrics,Obstetrics,Dermatology,Psychiatry", "ICU,Laboratory,Pharmacy,Ambulance,Dialysis", "24/7"],
        ["Ridge Hospital", "Government Hospital", "Castle Rd, Accra", "Greater Accra", "+233 302 666 444", "info@ridgehospital.gov.gh", 4.4, 632, "General Medicine,Surgery,Maternity,Pediatrics,Emergency", "Laboratory,Pharmacy,Ambulance,Blood Bank", "24/7"],
        ["Tema General Hospital", "Government Hospital", "Tema Community 1", "Greater Accra", "+233 303 202 871", "info@temahospital.gov.gh", 4.3, 445, "General Medicine,Surgery,Maternity,Pediatrics,Emergency,Orthopedics", "Laboratory,Pharmacy,Ambulance", "24/7"],
        ["Trust Hospital", "Private Hospital", "Osu, Accra", "Greater Accra", "+233 302 761 631", "info@trusthospital.com.gh", 4.8, 892, "General Medicine,Surgery,Pediatrics,Cardiology,Maternity,Oncology", "ICU,MRI,CT Scan,Laboratory,Pharmacy,VIP Rooms", "24/7"],
        ["Nyaho Medical Centre", "Private Hospital", "Airport Residential, Accra", "Greater Accra", "+233 302 775 291", "info@nyaho.com.gh", 4.9, 1023, "General Medicine,Surgery,Pediatrics,Cardiology,Neurology,Dermatology", "ICU,MRI,CT Scan,Laboratory,Pharmacy,Telemedicine", "24/7"],
        ["Holy Family Hospital", "Mission Hospital", "Nkawkaw, Eastern Region", "Eastern", "+233 342 290 012", "info@holyfamily.gov.gh", 4.5, 378, "General Medicine,Surgery,Maternity,Pediatrics,Emergency", "Laboratory,Pharmacy,Ambulance", "24/7"],
        ["Suntreso Government Hospital", "Government Hospital", "Suntreso, Kumasi", "Ashanti", "+233 322 033 456", "info@suntreso.gov.gh", 4.2, 289, "General Medicine,Surgery,Maternity,Pediatrics,Emergency", "Laboratory,Pharmacy,Ambulance", "24/7"],
    ];
    foreach ($hospitals as $h) { $stmt->execute($h); }
}

echo json_encode(["message" => "Doctors and hospitals setup complete!"]);
