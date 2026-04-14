<?php
require_once "db.php";

$password = password_hash("Doctor1234", PASSWORD_BCRYPT);

$doctors = [
    1  => "dr.ama.owusu@jdnhealth.com",
    2  => "dr.kweku.asante@jdnhealth.com",
    4  => "dr.yaw.darko@jdnhealth.com",
    5  => "dr.abena.mensah@jdnhealth.com",
    6  => "dr.kofi.boateng@jdnhealth.com",
    7  => "dr.akosua.frimpong@jdnhealth.com",
    8  => "dr.nana.adjei@jdnhealth.com",
    9  => "dr.kwame.osei@jdnhealth.com",
    10 => "dr.adwoa.asante@jdnhealth.com",
];

$stmt = $pdo->prepare("UPDATE doctors SET email = ?, password = ? WHERE id = ?");
foreach ($doctors as $id => $email) {
    $stmt->execute([$email, $password, $id]);
}

$stmt2 = $pdo->prepare("SELECT id, name, email FROM doctors ORDER BY id");
$stmt2->execute([]);
$result = $stmt2->fetchAll();
echo json_encode(["message" => "Doctor logins seeded!", "doctors" => $result]);