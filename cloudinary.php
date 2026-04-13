<?php
define("CLOUDINARY_CLOUD", getenv("CLOUDINARY_CLOUD") ?: "djpcb55af");
define("CLOUDINARY_KEY",   getenv("CLOUDINARY_KEY")   ?: "686989927759478");
define("CLOUDINARY_SECRET",getenv("CLOUDINARY_SECRET") ?: "Vf2sgSAQS0caAlbVrtqNxxn0s58");

function uploadToCloudinary($fileTmpPath, $folder = "jdnhealth", $publicId = null) {
    $timestamp = time();
    $params = ["folder" => $folder, "timestamp" => $timestamp];
    if ($publicId) $params["public_id"] = $publicId;
    ksort($params);
    $sigString = urldecode(http_build_query($params)) . CLOUDINARY_SECRET;
    $signature = sha1($sigString);

    $postFields = [
        "file"          => new CURLFile($fileTmpPath),
        "upload_preset" => "jdnhealth_backup",
        "folder"        => $folder,
        "timestamp"     => $timestamp,
        "api_key"       => CLOUDINARY_KEY,
        "signature"     => $signature,
    ];
    if ($publicId) $postFields["public_id"] = $publicId;

    $ch = curl_init("https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD . "/auto/upload");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) throw new Exception("Cloudinary CURL error: $err");
    $data = json_decode($response, true);
    if (isset($data["error"])) throw new Exception("Cloudinary error: " . $data["error"]["message"]);
    return $data["secure_url"];
}