<?php

function sendSMS($message, $phone_numbers) {
    // API endpoint specified in the curl command
        $api_endpoint = "https://api.sms-gate.app/3rdparty/v1/message"; 
            
                // Your Cloud Server credentials from previous screenshots
                    $username = "BD3ROQ";
                        $password = "ariesdavebautista";

                            // Ensure phone_numbers is an array as expected by the API
                                if (!is_array($phone_numbers)) {
                                        $phone_numbers = [$phone_numbers];
                                            }

                                                // Prepare the data payload as a PHP array
                                                    $data = [
                                                            "message" => $message,
                                                                    "phoneNumbers" => $phone_numbers
                                                                        ];

                                                                            // Encode the data to JSON string for the -d (data) option
                                                                                $json_payload = json_encode($data);

                                                                                    $ch = curl_init();

                                                                                        // Set the URL
                                                                                            curl_setopt($ch, CURLOPT_URL, $api_endpoint);

                                                                                                // Set the request method to POST
                                                                                                    curl_setopt($ch, CURLOPT_POST, 1);

                                                                                                        // Set the JSON payload for the POST request
                                                                                                            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);

                                                                                                                // Return the response as a string instead of outputting it
                                                                                                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                                                                                                                        // Set the Basic Authentication credentials (-u <username>:<password>)
                                                                                                                            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

                                                                                                                                // Set the Content-Type header (-H "Content-Type: application/json")
                                                                                                                                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                                                                                                                            'Content-Type: application/json'
                                                                                                                                                ));

                                                                                                                                                    // Execute the cURL session
                                                                                                                                                        $response = curl_exec($ch);

                                                                                                                                                            // Get HTTP status code and cURL error information for debugging
                                                                                                                                                                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                                                                                                                                                    $curl_errno = curl_errno($ch);
                                                                                                                                                                        $curl_error = curl_error($ch);

                                                                                                                                                                            // Close cURL session
                                                                                                                                                                                curl_close($ch);

                                                                                                                                                                                    // Basic error handling (no comments as requested)
                                                                                                                                                                                        if ($curl_errno) {
                                                                                                                                                                                                error_log("cURL Error ({$curl_errno}): {$curl_error}");
                                                                                                                                                                                                        return false;
                                                                                                                                                                                                            }

                                                                                                                                                                                                                if ($http_code >= 200 && $http_code < 300) {
                                                                                                                                                                                                                        $api_response = json_decode($response, true);
                                                                                                                                                                                                                                if (json_last_error() === JSON_ERROR_NONE && isset($api_response['id'])) {
                                                                                                                                                                                                                                            error_log("SMS successfully sent (HTTP {$http_code}). Message ID: " . $api_response['id']);
                                                                                                                                                                                                                                                        return true;
                                                                                                                                                                                                                                                                } else {
                                                                                                                                                                                                                                                                            error_log("SMS sent (HTTP {$http_code}), but unexpected API response format: " . $response);
                                                                                                                                                                                                                                                                                        return false;
                                                                                                                                                                                                                                                                                                }
                                                                                                                                                                                                                                                                                                    } else {
                                                                                                                                                                                                                                                                                                            error_log("API request failed with HTTP status {$http_code}. Response: " . $response);
                                                                                                                                                                                                                                                                                                                    return false;
                                                                                                                                                                                                                                                                                                                        }
                                                                                                                                                                                                                                                                                                                        }

                                                                                                                                                                                                                                                                                                                        ?>
                                                                                                                                                                                                                                                                                                                        