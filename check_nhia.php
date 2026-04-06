<?php require_once "db.php"; $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type=`"table`"")->fetchAll(); echo json_encode($tables);
