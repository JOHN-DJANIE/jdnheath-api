<?php
require_once "cors.php";
echo json_encode([
    "message" => "JDN Health API is running!",
    "version" => "1.0.0",
    "endpoints" => [
        "auth" => "/auth.php",
        "vitals" => "/vitals.php",
        "appointments" => "/appointments.php",
        "medications" => "/medications.php",
        "records" => "/records.php"
    ]
]);
