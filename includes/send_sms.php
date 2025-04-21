<?php
function sendSMS($message, $phone_numbers) {
    $local_ip = "192.168.100.10";  // local address: ...
    $username = "sms";
    $password = "ariesdave";

    $data = [
        "message" => $message,
        "phoneNumbers" => $phone_numbers
    ];

    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n" .
                        "Authorization: Basic " . base64_encode("$username:$password"),
            "method" => "POST",
            "content" => json_encode($data),
        ]
    ];

    try {
        $context = stream_context_create($options);
        $response = file_get_contents("http://$local_ip:8080/message", false, $context);
        return $response;
    } catch (Exception $e) {
        error_log("SMS sending failed: " . $e->getMessage());
        return false;
    }
}
?>