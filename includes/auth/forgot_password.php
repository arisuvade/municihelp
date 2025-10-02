<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPhone = trim($_POST['phone']);
    
    // Format phone number to +63
    $phone = $inputPhone;
    if (strpos($phone, '+63') !== 0) {
        $phone = ltrim($phone, '0');
        $phone = ltrim($phone, '63');
        $phone = '+63' . $phone;
    }

    // Validate number
    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit Philippine mobile number (e.g., 9123456789)";
    } else {
        // Initialize statements
        $stmt = $update = null;
        
        try {
            // Check user
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            if (!$stmt) throw new Exception($conn->error);
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Generate OTP
                $otp = rand(100000, 999999);
                $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
                $otp_expiry = date('Y-m-d H:i:s', time() + 30); // 5 minutes

                // Update user with OTP
                $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE phone = ?");
                if (!$update) throw new Exception($conn->error);
                $update->bind_param("sss", $otp_hash, $otp_expiry, $phone);
                
                if ($update->execute()) {
                    // Send OTP via SMS
                    require '../../includes/send_sms.php';
                    $message = "Your MuniciHelp password reset code is: $otp. This code will expire in 5 minutes.";
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
                        $error = "Failed to send OTP via SMS. Please try again later.";
                    }
                } else {
                    $error = "Error generating OTP. Please try again.";
                }
            } else {
                $error = "Phone number not registered.";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        } finally {
            // Close statements
            if ($stmt) $stmt->close();
            if ($update) $update->close();
        }
    }
}

$pageTitle = 'Forgot Password';
$isAuthPage = true;
include '../../includes/header.php';
?>
<link rel="icon" href="../../favicon.ico" type="image/x-icon">

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">Forgot Password</h2>
        <p class="text-muted">Enter your registered phone number to reset your password</p>
    </div>
    
    <div id="errorContainer">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger floating-alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>
    
    <form method="POST" id="forgotForm">
        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <div class="input-group">
                <span class="input-group-text">+63</span>
                <input type="tel" class="form-control" id="phone" name="phone" required 
                       placeholder="9123456789" pattern="\d{10}" maxlength="10"
                       title="Please enter your 10-digit mobile number (e.g., 9123456789)"
                       value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
            </div>
            <small class="form-text text-muted">Enter your 10-digit mobile number (e.g. 9123456789)</small>
        </div>
        <button type="submit" class="btn btn-munici-green w-100">Send Reset Code</button>
    </form>
    
    <p class="text-center mt-3 small text-muted">Remember your password? <a href="login.php" class="text-munici-green">Login here</a></p>
</div>

<script>
    // Client side validation
    document.getElementById('forgotForm').addEventListener('submit', function(e) {
        const phoneInput = document.getElementById('phone');
        const phone = phoneInput.value;
        
        if (!/^\d{10}$/.test(phone)) {
            e.preventDefault();
            showError('Please enter a valid 10-digit mobile number (e.g., 9123456789)');
            return false;
        }
        
        return true;
    });

    function showError(message) {
        const errorContainer = document.getElementById('errorContainer');
        errorContainer.innerHTML = '';
        
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger floating-alert';
        alert.textContent = message;
        errorContainer.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
</script>

<style>
.floating-alert {
    position: relative;
    width: 100%;
    margin-bottom: 1rem;
    animation: fadeInOut 5s ease-in-out;
    opacity: 1;
}

@keyframes fadeInOut {
    0%, 100% { opacity: 0; transform: translateY(-20px); }
    10%, 90% { opacity: 1; transform: translateY(0); }
}
</style>

<?php include '../../includes/footer.php'; ?>