<?php
$apiKey = "3d8b1f2d5abae45232f19b0f312c6dab7506a4536f4e04086053562ba28f6f35";
$ch = curl_init("https://api.daily.co/v1/rooms");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $apiKey]);
$result = curl_exec($ch);
curl_close($ch);
echo $result;
