<?php
session_start();
include '../db.php';
require '../../includes/send_sms.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    switch ($action) {
        case 'register':
            $phone = $_POST['phone'];
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $otp = rand(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

            // check user
            $check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            $check->bind_param("s", $phone);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Ang numero ng telepono ay naka-rehistro na.']);
                exit;
            }

            // insert new user
            $stmt = $conn->prepare("INSERT INTO users (phone, password_hash, otp_hash, otp_expiry) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $phone, $password, $otp_hash, $otp_expiry);

            if ($stmt->execute()) {
                // send otp via sms
                $message = "Your MuniciHelp verification code is: $otp. This code will expire in 5 minutes.";
                $phone_numbers = [$phone];
                
                if (sendSMS($message, $phone_numbers) !== false) {
                    $_SESSION['otp_verification'] = [
                        'phone' => $phone,
                        'type' => 'registration'
                    ];
                    echo json_encode(['status' => 'success', 'redirect' => 'verify.php']);
                } else {
                    $conn->query("DELETE FROM users WHERE phone = '$phone'");
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
            }
            break;

        case 'login':
            $phone = $_POST['phone'];
            $password = $_POST['password'];

            $stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM users WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->bind_result($id, $hashed_password, $is_verified);
            $stmt->fetch();
            $stmt->close();

            if ($hashed_password && password_verify($password, $hashed_password)) {
                if (!$is_verified) {
                    echo json_encode(['status' => 'error', 'message' => 'Account not verified', 'redirect' => 'verify.php']);
                    exit;
                }

                // generate login otp
                $otp = rand(100000, 999999);
                $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
                $otp_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

                $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE id = ?");
                $update->bind_param("ssi", $otp_hash, $otp_expiry, $id);

                // send otp via sms
                $message = "Your MuniciHelp login verification code is: $otp. This code will expire in 5 minutes.";
                $phone_numbers = [$phone];
                
                if ($update->execute() && sendSMS($message, $phone_numbers) !== false) {
                    $_SESSION['otp_verification'] = [
                        'id' => $id,
                        'phone' => $phone,
                        'type' => 'login'
                    ];
                    echo json_encode(['status' => 'success', 'redirect' => 'verify.php']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid phone number or password']);
            }
            break;

        case 'verify_otp':
            $otp = implode('', $_POST['otp']);
            
            if (!isset($_SESSION['otp_verification'])) {
                echo json_encode(['status' => 'error', 'message' => 'Session expired', 'redirect' => 'login.php']);
                exit;
            }

            $verification = $_SESSION['otp_verification'];
            $phone = $verification['phone'];
            $type = $verification['type'];

            if ($type === 'registration') {
                $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ? AND is_verified = 0");
                $stmt->bind_param("s", $phone);
            } else if ($type === 'login') {
                $id = $verification['id'];
                $stmt = $conn->prepare("SELECT otp_hash, otp_expiry FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
            } else { // password_reset
                $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ?");
                $stmt->bind_param("s", $phone);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($otp, $user['otp_hash']) && strtotime($user['otp_expiry']) > time()) {
                if ($type === 'registration') {
                    $update = $conn->prepare("UPDATE users SET is_verified = 1, otp_hash = NULL, otp_expiry = NULL WHERE id = ?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();
                    $update->close();
                }

                if ($type === 'password_reset') {
                    $_SESSION['password_reset_user'] = $user['id'];
                    echo json_encode(['status' => 'success', 'redirect' => 'reset_password.php']);
                } else {
                    $_SESSION['user_id'] = $type === 'registration' ? $user['id'] : $verification['id'];
                    echo json_encode(['status' => 'success', 'redirect' => '../index.php']);
                }
                
                unset($_SESSION['otp_verification']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP']);
            }
            break;

        case 'resend_otp':
    if (!isset($_SESSION['otp_verification'])) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
        exit;
    }

    $verification = $_SESSION['otp_verification'];
    $phone = $verification['phone'];
    $type = $verification['type'];
    $otp = rand(100000, 999999);
    $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
    $otp_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    if ($type === 'registration') {
        $stmt = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE phone = ? AND is_verified = 0");
        $stmt->bind_param("sss", $otp_hash, $otp_expiry, $phone);
    } else if ($type === 'password_reset') {
        $stmt = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE phone = ?");
        $stmt->bind_param("sss", $otp_hash, $otp_expiry, $phone);
    } else { // login
        $id = $verification['id'];
        $stmt = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE id = ?");
        $stmt->bind_param("ssi", $otp_hash, $otp_expiry, $id);
    }

    // send otp via sms
    $message = "Your MuniciHelp verification code is: $otp. This code will expire in 5 minutes.";
    $phone_numbers = [$phone];
    
    if ($stmt->execute() && sendSMS($message, $phone_numbers) !== false) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to resend OTP']);
    }
    $stmt->close();
    break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

$conn->close();
?>