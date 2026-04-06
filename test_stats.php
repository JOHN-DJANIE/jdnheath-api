<?php
require_once "db.php";

$doctorId = 1;
$today = date("Y-m-d");

$t = $pdo->prepare("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = ? AND appointment_date = ?");
$t->execute([$doctorId, $today]);
$todayCount = $t->fetch()["count"];

$p = $pdo->prepare("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = ? AND status = ?");
$p->execute([$doctorId, "pending"]);
$pendingCount = $p->fetch()["count"];

$all = $pdo->prepare("SELECT * FROM consultations WHERE doctor_id = ?");
$all->execute([$doctorId]);
$allConsults = $all->fetchAll();

echo json_encode([
    "today" => $today,
    "today_count" => $todayCount,
    "pending_count" => $pendingCount,
    "all_consultations" => $allConsults
]);
