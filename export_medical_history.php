<?php
error_reporting(0);
ini_set("display_errors", 0);
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";
require_once "vendor/autoload.php";

$decoded = verifyToken();
$userId  = $decoded["id"];

// Fetch all patient data
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

$vitals = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? AND deleted_at IS NULL ORDER BY recorded_at DESC LIMIT 10");
$vitals->execute([$userId]);
$vitals = $vitals->fetchAll();

$medications = $pdo->prepare("SELECT * FROM medications WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$medications->execute([$userId]);
$medications = $medications->fetchAll();

$appointments = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND deleted_at IS NULL ORDER BY appointment_date DESC LIMIT 10");
$appointments->execute([$userId]);
$appointments = $appointments->fetchAll();

$records = $pdo->prepare("SELECT * FROM records WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10");
$records->execute([$userId]);
$records = $records->fetchAll();

$consultations = $pdo->prepare("SELECT * FROM consultations WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10");
$consultations->execute([$userId]);
$consultations = $consultations->fetchAll();

// Create PDF
$pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);
$pdf->SetCreator("JDNHealth GH");
$pdf->SetAuthor("JDNHealth GH");
$pdf->SetTitle("Medical History - " . $user["name"]);
$pdf->SetSubject("Patient Medical History Report");
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Colors
$navyR = 4; $navyG = 28; $navyB = 44;
$tealR = 13; $tealG = 122; $tealB = 95;
$goldR = 201; $goldG = 150; $goldB = 42;

// HEADER
$pdf->SetFillColor($navyR, $navyG, $navyB);
$pdf->Rect(0, 0, 210, 40, "F");
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont("helvetica", "B", 22);
$pdf->SetXY(15, 8);
$pdf->Cell(0, 10, "JDNHealth GH", 0, 1, "L");
$pdf->SetFont("helvetica", "", 11);
$pdf->SetXY(15, 20);
$pdf->Cell(0, 8, "Patient Medical History Report", 0, 1, "L");
$pdf->SetFont("helvetica", "", 9);
$pdf->SetXY(15, 30);
$pdf->Cell(0, 6, "Generated: " . date("d M Y, H:i") . " | Confidential", 0, 1, "L");

// PATIENT INFO BOX
$pdf->SetY(48);
$pdf->SetFillColor(240, 248, 255);
$pdf->SetDrawColor($tealR, $tealG, $tealB);
$pdf->RoundedRect(15, 48, 180, 35, 3, "1111", "DF");
$pdf->SetTextColor($navyR, $navyG, $navyB);
$pdf->SetFont("helvetica", "B", 13);
$pdf->SetXY(20, 52);
$pdf->Cell(0, 8, "Patient Information", 0, 1);
$pdf->SetFont("helvetica", "", 10);
$pdf->SetXY(20, 60);
$pdf->SetTextColor(60, 60, 60);

$info = [
    ["Name", $user["name"] ?? "N/A", "Date of Birth", $user["date_of_birth"] ?? "N/A"],
    ["Email", $user["email"] ?? "N/A", "Phone", $user["phone"] ?? "N/A"],
    ["Gender", $user["gender"] ?? "N/A", "Blood Type", $user["blood_type"] ?? "N/A"],
    ["NHIA No.", $user["nhia_number"] ?? "N/A", "Address", $user["address"] ?? "N/A"],
];

$y = 60;
foreach ($info as $row) {
    $pdf->SetXY(20, $y);
    $pdf->SetFont("helvetica", "B", 9); $pdf->Cell(25, 5, $row[0] . ":", 0);
    $pdf->SetFont("helvetica", "", 9);  $pdf->Cell(60, 5, $row[1], 0);
    $pdf->SetFont("helvetica", "B", 9); $pdf->Cell(25, 5, $row[2] . ":", 0);
    $pdf->SetFont("helvetica", "", 9);  $pdf->Cell(60, 5, $row[3], 0);
    $y += 6;
}

// Section helper
function addSection($pdf, &$y, $title, $tealR, $tealG, $tealB, $navyR, $navyG, $navyB) {
    if ($y > 240) { $pdf->AddPage(); $y = 15; }
    $pdf->SetFillColor($tealR, $tealG, $tealB);
    $pdf->Rect(15, $y, 180, 8, "F");
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont("helvetica", "B", 11);
    $pdf->SetXY(18, $y + 1);
    $pdf->Cell(0, 6, $title, 0, 1);
    $pdf->SetTextColor(40, 40, 40);
    $y += 12;
}

function addRow($pdf, &$y, $label, $value, $shade = false) {
    if ($y > 265) { $pdf->AddPage(); $y = 15; }
    if ($shade) $pdf->SetFillColor(248, 250, 252);
    else $pdf->SetFillColor(255, 255, 255);
    $pdf->SetXY(15, $y);
    $pdf->SetFont("helvetica", "B", 9);
    $pdf->Cell(55, 6, $label, 0, 0, "L", true);
    $pdf->SetFont("helvetica", "", 9);
    $pdf->MultiCell(125, 6, $value ?: "N/A", 0, "L", true);
    $y += 6;
}

$y = 90;

// LATEST VITALS
addSection($pdf, $y, "Latest Vitals", $tealR, $tealG, $tealB, $navyR, $navyG, $navyB);
if (empty($vitals)) {
    addRow($pdf, $y, "Status", "No vitals recorded yet.");
} else {
    $v = $vitals[0];
    $rows = [
        ["Blood Pressure", ($v["blood_pressure_systolic"] ?? "N/A") . "/" . ($v["blood_pressure_diastolic"] ?? "N/A") . " mmHg"],
        ["Heart Rate",     ($v["heart_rate"] ?? "N/A") . " bpm"],
        ["Temperature",    ($v["temperature"] ?? "N/A") . " C"],
        ["BMI",            $v["bmi"] ?? "N/A"],
        ["Blood Sugar",    ($v["blood_sugar"] ?? "N/A") . " mmol/L"],
        ["O2 Saturation",  ($v["oxygen_saturation"] ?? "N/A") . "%"],
        ["Weight",         ($v["weight"] ?? "N/A") . " kg"],
        ["Height",         ($v["height"] ?? "N/A") . " cm"],
        ["Recorded At",    $v["recorded_at"] ?? "N/A"],
    ];
    $shade = false;
    foreach ($rows as $r) { addRow($pdf, $y, $r[0], $r[1], $shade); $shade = !$shade; }
}

// MEDICATIONS
$y += 4;
addSection($pdf, $y, "Medications (" . count($medications) . ")", $tealR, $tealG, $tealB, $navyR, $navyG, $navyB);
if (empty($medications)) {
    addRow($pdf, $y, "Status", "No medications recorded.");
} else {
    $shade = false;
    foreach ($medications as $m) {
        addRow($pdf, $y, $m["name"] ?? "N/A", "Dosage: " . ($m["dosage"] ?? "N/A") . " | Frequency: " . ($m["frequency"] ?? "N/A") . " | Active: " . ($m["is_active"] ? "Yes" : "No"), $shade);
        $shade = !$shade;
    }
}

// APPOINTMENTS
$y += 4;
addSection($pdf, $y, "Recent Appointments (" . count($appointments) . ")", $tealR, $tealG, $tealB, $navyR, $navyG, $navyB);
if (empty($appointments)) {
    addRow($pdf, $y, "Status", "No appointments on record.");
} else {
    $shade = false;
    foreach ($appointments as $a) {
        addRow($pdf, $y, $a["doctor_name"] ?? "N/A", "Date: " . ($a["appointment_date"] ?? "N/A") . " " . ($a["appointment_time"] ?? "") . " | Specialty: " . ($a["specialty"] ?? "N/A") . " | Status: " . ($a["status"] ?? "N/A"), $shade);
        $shade = !$shade;
    }
}

// CONSULTATIONS
$y += 4;
addSection($pdf, $y, "Consultations (" . count($consultations) . ")", $tealR, $tealG, $tealB, $navyR, $navyG, $navyB);
if (empty($consultations)) {
    addRow($pdf, $y, "Status", "No consultations on record.");
} else {
    $shade = false;
    foreach ($consultations as $c) {
        addRow($pdf, $y, $c["doctor_name"] ?? "N/A", "Type: " . ($c["consultation_type"] ?? "N/A") . " | Date: " . ($c["appointment_date"] ?? "N/A") . " | Status: " . ($c["status"] ?? "N/A") . " | Fee: GH " . ($c["total_price"] ?? "N/A"), $shade);
        $shade = !$shade;
    }
}

// LAB RESULTS
$y += 4;
addSection($pdf, $y, "Lab Results & Records (" . count($records) . ")", $tealR, $tealG, $tealB, $navyR, $navyG, $navyB);
if (empty($records)) {
    addRow($pdf, $y, "Status", "No lab results on record.");
} else {
    $shade = false;
    foreach ($records as $r) {
        addRow($pdf, $y, $r["title"] ?? "N/A", "Result: " . ($r["result"] ?? "N/A") . " | Date: " . ($r["test_date"] ?? $r["created_at"] ?? "N/A") . " | Notes: " . ($r["notes"] ?? "N/A"), $shade);
        $shade = !$shade;
    }
}

// FOOTER
$pageCount = $pdf->getNumPages();
for ($i = 1; $i <= $pageCount; $i++) {
    $pdf->setPage($i);
    $pdf->SetFillColor($navyR, $navyG, $navyB);
    $pdf->Rect(0, 285, 210, 15, "F");
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont("helvetica", "", 8);
    $pdf->SetXY(15, 288);
    $pdf->Cell(90, 5, "JDNHealth GH | Ghana Premier Digital Health Platform", 0, 0, "L");
    $pdf->Cell(90, 5, "Page $i of $pageCount | Confidential Medical Record", 0, 0, "R");
}

// Output PDF
$filename = "JDNHealth_Medical_History_" . preg_replace("/[^a-zA-Z0-9]/", "_", $user["name"]) . "_" . date("Y-m-d") . ".pdf";
$pdf->Output($filename, "D"); // D = download