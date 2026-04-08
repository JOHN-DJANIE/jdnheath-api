<?php
echo json_encode([
    "php_version" => phpversion(),
    "pdo_drivers" => PDO::getAvailableDrivers(),
    "pdo_pgsql" => extension_loaded("pdo_pgsql"),
    "pgsql" => extension_loaded("pgsql"),
]);