<?php
$secret = $_GET["secret"] ?? "";
if ($secret !== "jdnhealth_backup_2024") {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized."]);
    exit;
}

require_once "db.php";

$tables = [
    "users","doctors","hospitals","appointments","consultations",
    "orders","order_items","products","medications","prescriptions",
    "records","vitals","lab_results","nhia_members","nhia_claims",
    "insurance_claims","doctor_availability","availability_slots",
    "doctor_earnings","earnings","platform_settings","sms_logs",
    "auth_logs","admin_logs","admin_users","admins","user_sessions"
];

$backup = [
    "meta" => [
        "created_at"  => date("Y-m-d H:i:s"),
        "database"    => "neondb",
        "platform"    => "JDNHealth GH",
        "table_count" => count($tables),
        "version"     => "1.0",
    ],
    "tables" => [],
];

$totalRows = 0;
$errors    = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM $table");
        $stmt->execute([]);
        $rows = $stmt->fetchAll();
        $backup["tables"][$table] = $rows;
        $totalRows += count($rows);
    } catch (Exception $e) {
        $errors[] = "$table: " . $e->getMessage();
        $backup["tables"][$table] = [];
    }
}

$backup["meta"]["total_rows"] = $totalRows;
$backup["meta"]["errors"]     = $errors;

$json     = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$filename = "jdnhealth_backup_" . date("Y-m-d_H-i-s") . ".json";

// Always save locally first
$localDir = __DIR__ . "/backups/";
if (!is_dir($localDir)) mkdir($localDir, 0755, true);

// Keep only last 7 backups locally
$existing = glob($localDir . "*.json");
if (count($existing) >= 7) {
    usort($existing, fn($a,$b) => filemtime($a) - filemtime($b));
    foreach (array_slice($existing, 0, count($existing) - 6) as $old) {
        unlink($old);
    }
}

$localPath = $localDir . $filename;
file_put_contents($localPath, $json);

// Try Cloudinary upload
$tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
file_put_contents($tmpPath, $json);

$cloud     = "djpcb55af";
$folder    = "jdnhealth/backups";

$ch = curl_init("https://api.cloudinary.com/v1_1/$cloud/raw/upload");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        "file"         => new CURLFile($tmpPath, "application/json", $filename),
        "upload_preset"=> "jdnhealth_backup",
        "folder"       => $folder,
    ],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
curl_close($ch);
if (file_exists($tmpPath)) unlink($tmpPath);

$result   = json_decode($response, true);
$cloudUrl = $result["secure_url"] ?? null;
$cloudErr = $result["error"]["message"] ?? null;

echo json_encode([
    "status"       => "success",
    "backup_file"  => $filename,
    "local_saved"  => true,
    "local_path"   => $localPath,
    "cloud_url"    => $cloudUrl,
    "cloud_status" => $cloudUrl ? "uploaded" : "failed - $cloudErr",
    "tables"       => count($tables),
    "total_rows"   => $totalRows,
    "errors"       => $errors,
    "created_at"   => date("Y-m-d H:i:s"),
    "note"         => "Neon also auto-backs up daily for 7 days. Local backups kept for 7 days.",
]);