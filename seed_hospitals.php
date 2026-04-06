<?php
require_once "db.php";
try {
    $hospitals = [
        ["Korle Bu Teaching Hospital", "Teaching Hospital", "Accra", "Greater Accra", "+233 302 674 301", "Emergency, Surgery, Cardiology, Oncology, Pediatrics, Maternity", 4.5, 1],
        ["37 Military Hospital", "Military Hospital", "Accra", "Greater Accra", "+233 302 776 111", "General Medicine, Emergency, Surgery, Orthopedics", 4.3, 1],
        ["Komfo Anokye Teaching Hospital", "Teaching Hospital", "Kumasi", "Ashanti", "+233 322 022 301", "Surgery, Pediatrics, Maternity, Cardiology, Neurology", 4.4, 1],
        ["Ridge Hospital", "Government Hospital", "Accra", "Greater Accra", "+233 302 665 401", "General Medicine, Maternity, Pediatrics, Emergency", 4.2, 1],
        ["Trust Hospital", "Private Hospital", "Accra", "Greater Accra", "+233 302 521 121", "General Medicine, Surgery, Diagnostics, Pharmacy", 4.6, 1],
        ["Lister Hospital", "Private Hospital", "Accra", "Greater Accra", "+233 302 784 300", "Surgery, Cardiology, Maternity, ICU", 4.5, 1],
        ["University of Ghana Medical Centre", "Teaching Hospital", "Accra", "Greater Accra", "+233 302 213 800", "General Medicine, Research, Specialist Care", 4.7, 1],
        ["Cape Coast Teaching Hospital", "Teaching Hospital", "Cape Coast", "Central", "+233 332 132 205", "General Medicine, Surgery, Pediatrics, Maternity", 4.2, 1],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO hospitals (name, type, location, region, phone, departments, rating, is_active) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($hospitals as $h) {
        $stmt->execute($h);
    }
    echo json_encode(["message" => "Hospitals seeded successfully!", "count" => count($hospitals)]);
} catch(Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}