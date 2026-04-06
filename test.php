<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "db.php";
require_once "auth_helper.php";

$user = ["id" => 1, "email" => "test@test.com", "role" => "patient"];
$token = generateToken($user);
echo json_encode(["token" => $token]);
