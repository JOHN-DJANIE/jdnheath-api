<?php
$file = __DIR__ . "/admin_auth.php";
$content = file_get_contents($file);

$old = 'elseif ($method === "POST" && $action === "broadcast") {
      verifyAdmin($pdo);
      require_once "sms.php";
      $data = json_decode(file_get_contents("php://input"), true);
      $message = $data["message"] ?? "";
      $target = $data["target"] ?? "all";
      echo json_encode(["message" => "Broadcast sent.", "sent" => $sent, "total" => count($users)]);
  }';

$new = 'elseif ($method === "POST" && $action === "broadcast") {
      verifyAdmin($pdo);
      require_once "sms.php";
      $data = json_decode(file_get_contents("php://input"), true);
      $message = $data["message"] ?? "";
      $target = $data["target"] ?? "all";
      if (!$message) { http_response_code(400); echo json_encode(["error" => "Message required."]); exit; }
      $sql = "SELECT phone, name FROM users WHERE phone IS NOT NULL AND phone != \'\'";
      if ($target === "verified") $sql .= " AND is_verified = TRUE";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([]);
      $users = $stmt->fetchAll();
      $sent = 0;
      foreach ($users as $user) {
          try { if (sendSMS($user["phone"], $message)) $sent++; } catch(Exception $e) {}
      }
      echo json_encode(["message" => "Broadcast sent.", "sent" => $sent, "total" => count($users)]);
  }';

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo json_encode(["fixed" => true, "length" => strlen($content)]);