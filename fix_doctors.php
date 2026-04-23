<?php
require_once "cors.php";
require_once "db.php";
// is_verified is integer in this DB, not boolean
$pdo->prepare("UPDATE doctors SET verification_status = ? WHERE is_verified = 1")->execute(["approved"]);
$count = $pdo->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'approved'")->fetchColumn();
echo json_encode(["fixed" => true, "approved_doctors" => $count]);
