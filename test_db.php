<?php
require_once "db.php";
echo json_encode(["status" => "Connected!", "db_type" => "PostgreSQL"]);