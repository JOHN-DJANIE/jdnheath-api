<?php
define("ARKESEL_API_KEY", "a0ROR2xxa3hwc3Jmb2Naak5aZkI");
define("SMS_SENDER", "JDNHealth");

function sendSMS($phone, $message) {
    // Format Ghana numbers
    $phone = preg_replace('/\s+/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '+233' . substr($phone, 1);
    }

    $url = "https://sms.arkesel.com/sms/api?action=send-sms&api_key=" . urlencode(ARKESEL_API_KEY) .
           "&to=" . urlencode($phone) .
           "&from=" . urlencode(SMS_SENDER) .
           "&sms=" . urlencode($message);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);
    return $response["code"] === "ok";
}

function sendVerificationSMS($phone, $code) {
    return sendSMS($phone, "JDNHealth GH: Your verification code is $code. Valid for 24 hours. Do not share.");
}

function sendLoginCodeSMS($phone, $code) {
    return sendSMS($phone, "JDNHealth GH: Your login code is $code. Valid for 10 minutes. Do not share this code.");
}

function sendPasswordResetSMS($phone, $code) {
    return sendSMS($phone, "JDNHealth GH: Your password reset code is $code. Valid for 15 minutes. Do not share.");
}

function sendAppointmentConfirmSMS($phone, $doctorName, $date, $time) {
    return sendSMS($phone, "JDNHealth GH: Appointment confirmed with $doctorName on $date at $time. Reply CANCEL to cancel.");
}

function sendAppointmentReminderSMS($phone, $doctorName, $date, $time) {
    return sendSMS($phone, "JDNHealth GH: Reminder - You have an appointment with $doctorName tomorrow $date at $time.");
}

function sendOrderConfirmSMS($phone, $orderId, $total) {
    return sendSMS($phone, "JDNHealth GH: Order #$orderId confirmed. Total: GHS $total. Your medicines will be delivered soon.");
}

function sendWelcomeSMS($phone, $name) {
    return sendSMS($phone, "Welcome to JDNHealth GH, $name! Ghana's premier healthcare platform. Book doctors, order medicines and manage your health records.");
}
