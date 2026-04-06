<?php
require_once "db.php";
try { $pdo->exec("ALTER TABLE consultations ADD COLUMN video_room_url TEXT"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE consultations ADD COLUMN video_room_name TEXT"); } catch (Exception $e) {}
echo json_encode(["message" => "Video columns added!"]);
