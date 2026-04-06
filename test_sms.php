<?php
$apiKey = "a0ROR2xxa3hwc3Jmb2Naak5aZkI";
$phone = "0593365380";
$message = "Hello from JDNHealth GH! SMS integration test.";

$url = "https://sms.arkesel.com/sms/api?action=send-sms&api_key=" . urlencode($apiKey) .
       "&to=" . urlencode($phone) .
       "&from=JDNHealth&sms=" . urlencode($message);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
curl_close($ch);

echo json_encode(["response" => json_decode($result), "raw" => $result]);
