<?php
require_once "cors.php";
require_once "db.php";
require_once "auth_helper.php";

$method  = $_SERVER["REQUEST_METHOD"];
$action  = $_GET["action"] ?? "";
$decoded = verifyToken();
$userId  = $decoded["id"];

// Biologically valid ranges
$ranges = [
    "blood_pressure_systolic"  => ["min" => 50,  "max" => 300,  "name" => "Systolic blood pressure",  "unit" => "mmHg"],
    "blood_pressure_diastolic" => ["min" => 30,  "max" => 200,  "name" => "Diastolic blood pressure", "unit" => "mmHg"],
    "heart_rate"               => ["min" => 20,  "max" => 300,  "name" => "Heart rate",               "unit" => "bpm"],
    "temperature"              => ["min" => 30.0,"max" => 45.0, "name" => "Body temperature",         "unit" => "°C"],
    "weight"                   => ["min" => 1,   "max" => 500,  "name" => "Weight",                   "unit" => "kg"],
    "height"                   => ["min" => 30,  "max" => 280,  "name" => "Height",                   "unit" => "cm"],
    "blood_sugar"              => ["min" => 1.0, "max" => 60.0, "name" => "Blood sugar",              "unit" => "mmol/L"],
    "oxygen_saturation"        => ["min" => 50,  "max" => 100,  "name" => "Oxygen saturation",        "unit" => "%"],
];

function validateVitals($data, $ranges) {
    $errors = [];
    foreach ($ranges as $field => $rule) {
        if (!isset($data[$field]) || $data[$field] === null || $data[$field] === "") continue;
        $val = floatval($data[$field]);
        if (!is_numeric($data[$field])) {
            $errors[] = "{$rule["name"]} must be a number.";
        } elseif ($val < $rule["min"] || $val > $rule["max"]) {
            $errors[] = "{$rule["name"]} must be between {$rule["min"]} and {$rule["max"]} {$rule["unit"]}. Got: $val.";
        }
    }
    // Systolic must be higher than diastolic
    if (isset($data["blood_pressure_systolic"]) && isset($data["blood_pressure_diastolic"])) {
        $sys = floatval($data["blood_pressure_systolic"]);
        $dia = floatval($data["blood_pressure_diastolic"]);
        if ($sys && $dia && $sys <= $dia) {
            $errors[] = "Systolic pressure ($sys) must be higher than diastolic pressure ($dia).";
        }
    }
    // At least one vital must be provided
    $provided = array_filter($data, fn($v) => $v !== null && $v !== "");
    $vitalFields = array_keys($ranges);
    $hasVital = false;
    foreach ($vitalFields as $f) {
        if (isset($data[$f]) && $data[$f] !== null && $data[$f] !== "") { $hasVital = true; break; }
    }
    if (!$hasVital) {
        $errors[] = "At least one vital sign must be provided.";
    }
    return $errors;
}

if ($method === "GET" && $action === "latest") {
    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? AND deleted_at IS NULL ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    echo json_encode(["vitals" => $stmt->fetch() ?: null]);
}

elseif ($method === "GET") {
    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? AND deleted_at IS NULL ORDER BY recorded_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(["vitals" => $stmt->fetchAll()]);
}

elseif ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validate
    $errors = validateVitals($data, $ranges);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(["error" => "Validation failed.", "details" => $errors]);
        exit;
    }

    // Calculate BMI if weight and height provided
    $w   = isset($data["weight"]) && $data["weight"] !== "" ? floatval($data["weight"]) : null;
    $h   = isset($data["height"]) && $data["height"] !== "" ? floatval($data["height"]) : null;
    $bmi = ($w && $h) ? round($w / (($h / 100) ** 2), 1) : null;

    // Warnings for abnormal but possible values
    $warnings = [];
    if (isset($data["heart_rate"]) && $data["heart_rate"] !== "") {
        $hr = floatval($data["heart_rate"]);
        if ($hr < 40 || $hr > 200) $warnings[] = "Heart rate of $hr bpm is outside normal range (40-200 bpm). Please consult a doctor.";
    }
    if (isset($data["oxygen_saturation"]) && $data["oxygen_saturation"] !== "") {
        $spo2 = floatval($data["oxygen_saturation"]);
        if ($spo2 < 95) $warnings[] = "Oxygen saturation of $spo2% is below normal (95-100%). Seek medical attention.";
    }
    if (isset($data["temperature"]) && $data["temperature"] !== "") {
        $temp = floatval($data["temperature"]);
        if ($temp > 38.0) $warnings[] = "Temperature of {$temp}°C indicates fever. Monitor closely.";
        if ($temp < 36.0) $warnings[] = "Temperature of {$temp}°C is below normal. Monitor closely.";
    }
    if ($bmi !== null) {
        if ($bmi < 18.5) $warnings[] = "BMI of $bmi indicates underweight.";
        elseif ($bmi >= 30) $warnings[] = "BMI of $bmi indicates obesity. Consider consulting a doctor.";
        elseif ($bmi >= 25) $warnings[] = "BMI of $bmi indicates overweight.";
    }

    $stmt = $pdo->prepare("INSERT INTO vitals (patient_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, temperature, weight, height, bmi, blood_sugar, oxygen_saturation) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $userId,
        isset($data["blood_pressure_systolic"])  && $data["blood_pressure_systolic"]  !== "" ? floatval($data["blood_pressure_systolic"])  : null,
        isset($data["blood_pressure_diastolic"]) && $data["blood_pressure_diastolic"] !== "" ? floatval($data["blood_pressure_diastolic"]) : null,
        isset($data["heart_rate"])               && $data["heart_rate"]               !== "" ? floatval($data["heart_rate"])               : null,
        isset($data["temperature"])              && $data["temperature"]              !== "" ? floatval($data["temperature"])              : null,
        $w, $h, $bmi,
        isset($data["blood_sugar"])              && $data["blood_sugar"]              !== "" ? floatval($data["blood_sugar"])              : null,
        isset($data["oxygen_saturation"])        && $data["oxygen_saturation"]        !== "" ? floatval($data["oxygen_saturation"])        : null,
    ]);

    echo json_encode([
        "message"  => "Vitals recorded successfully.",
        "id"       => $pdo->lastInsertId(),
        "bmi"      => $bmi,
        "warnings" => $warnings,
    ]);
}

elseif ($method === "DELETE") {
    $id   = $_GET["id"] ?? null;
    $stmt = $pdo->prepare("UPDATE vitals SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND patient_id = ?");
    $stmt->execute([$id, $userId]);
    echo json_encode(["message" => "Deleted."]);
}

else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found."]);
}

