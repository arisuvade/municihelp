<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPhone = trim($_POST['phone']);
    
    // add +63
    $phone = $inputPhone;
    if (strpos($phone, '+63') !== 0) {
        $phone = ltrim($phone, '0');
        $phone = ltrim($phone, '63');
        $phone = '+63' . $phone;
    }

    // validate number
    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit Philippine mobile number (e.g., 9123456789)";
    } else {
        // check user
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // generate otp
            $otp = rand(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

            // update user with otp
            $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE phone = ?");
            $update->bind_param("sss", $otp_hash, $otp_expiry, $phone);
            
            if ($update->execute()) {
                // send otp via sms
                require '../../includes/send_sms.php';
                $message = "Your MuniciHelp password reset code is: $otp";
                $phone_numbers = [$phone];
                
                $smsResponse = sendSMS($message, $phone_numbers);
                
                if ($smsResponse !== false) {
                    $_SESSION['otp_verification'] = [
                        'phone' => $phone,
                        'type' => 'password_reset'
                    ];
                    header("Location: verify.php");
                    exit();
                } else {
                    $error = "Hindi naipadala ang OTP sa SMS. Mangyaring subukan ulit mamaya.";
                }
            } else {
                $error = "Error generating OTP. Please try again.";
            }
            $update->close();
        } else {
            $error = "Phone number not registered.";
        }
        $stmt->close();
    }
}

$pageTitle = 'Forgot Password';
$isAuthPage = true;
include '../../includes/header.php';
?>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">Forgot Password</h2>
        <p class="text-muted">Ilagay ang iyong rehistradong numero ng telepono upang i-reset ang iyong password.</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <div class="input-group">
                <span class="input-group-text">+63</span>
                <input type="tel" class="form-control" id="phone" name="phone" required 
                       placeholder="9123456789" pattern="\d{10}" maxlength="10"
                       title="Please enter your 10-digit mobile number (e.g., 9123456789)">
            </div>
            <small class="form-text text-muted">Ilagay ang iyong 10-digit na mobile number (hal. 9123456789)</small>
        </div>
        <button type="submit" class="btn btn-munici-green w-100">Send Reset Code</button>
    </form>
    
    <p class="text-center mt-3">Naalala mo na ang iyong password? <a href="login.php">Mag-login dito</a></p>
</div>

<?php include '../../includes/footer.php'; ?>