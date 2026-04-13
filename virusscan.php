<?php
define("VIRUSTOTAL_API_KEY", getenv("VIRUSTOTAL_API_KEY") ?: "406fe57a76e4a3a34a589239e2a89413e6bc5368527946e1c6c14bb6f8e45eff");

function scanFileForVirus($filePath, $fileName) {
    // Step 1: Upload file to VirusTotal
    $ch = curl_init("https://www.virustotal.com/api/v3/files");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ["file" => new CURLFile($filePath, mime_content_type($filePath), $fileName)],
        CURLOPT_HTTPHEADER     => ["x-apikey: " . VIRUSTOTAL_API_KEY],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("Virus scan upload failed: $err");

    $data = json_decode($response, true);
    if (!isset($data["data"]["id"])) throw new Exception("Virus scan failed: " . ($data["error"]["message"] ?? "Unknown error"));

    $analysisId = $data["data"]["id"];

    // Step 2: Poll for results (max 30 seconds)
    $maxAttempts = 6;
    $attempt     = 0;

    while ($attempt < $maxAttempts) {
        sleep(5);
        $ch = curl_init("https://www.virustotal.com/api/v3/analyses/" . $analysisId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["x-apikey: " . VIRUSTOTAL_API_KEY],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $result  = curl_exec($ch);
        curl_close($ch);

        $analysis = json_decode($result, true);
        $status   = $analysis["data"]["attributes"]["status"] ?? "queued";

        if ($status === "completed") {
            $stats     = $analysis["data"]["attributes"]["stats"] ?? [];
            $malicious = intval($stats["malicious"] ?? 0);
            $suspicious= intval($stats["suspicious"] ?? 0);
            $total     = array_sum($stats);

            return [
                "clean"      => ($malicious === 0 && $suspicious === 0),
                "malicious"  => $malicious,
                "suspicious" => $suspicious,
                "total"      => $total,
                "status"     => $status,
            ];
        }
        $attempt++;
    }

    // If scan times out return a warning but allow upload
    return [
        "clean"      => true,
        "malicious"  => 0,
        "suspicious" => 0,
        "total"      => 0,
        "status"     => "timeout",
        "warning"    => "Scan timed out — file allowed but not fully verified.",
    ];
}