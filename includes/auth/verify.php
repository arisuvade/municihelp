<?php
session_start();
include '../db.php';

if (!isset($_SESSION['otp_verification'])) {
    header("Location: login.php");
    exit();
}

$verification = $_SESSION['otp_verification'];
$phone = $verification['phone'];
$type = $verification['type']; 

// Check if OTP is already expired
$is_expired = false;
$remaining_time = 0;

if ($type === 'registration') {
    $stmt = $conn->prepare("SELECT otp_expiry FROM users WHERE phone = ? AND is_verified = 0");
    $stmt->bind_param("s", $phone);
} else if ($type === 'login') {
    $id = $verification['id'];
    $stmt = $conn->prepare("SELECT otp_expiry FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
} else { // password_reset
    $stmt = $conn->prepare("SELECT otp_expiry FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
}

$stmt->execute();
$stmt->bind_result($otp_expiry);
$stmt->fetch();
$stmt->close();

// Calculate remaining time in seconds
$remaining_time = strtotime($otp_expiry) - time();
if ($remaining_time < 0) {
    $remaining_time = 0; // Already expired
    $is_expired = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = implode('', $_POST['otp']);
    
    // Check if OTP is expired first
    if ($is_expired) {
        $_SESSION['otp_error'] = "OTP has expired. Please request a new one.";
        header("Location: verify.php");
        exit();
    } else {
        // Direct verification instead of using cURL
        if ($type === 'password_reset') {
            // Handle password reset verification
            $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->bind_result($id, $otp_hash, $otp_expiry);
            $stmt->fetch();
            $stmt->close();

            // Check if OTP is expired
            if (strtotime($otp_expiry) < time()) {
                $_SESSION['otp_error'] = "OTP has expired. Please request a new one.";
                header("Location: verify.php");
                exit();
            } elseif ($otp_hash && password_verify($otp, $otp_hash)) {
                $_SESSION['password_reset_user'] = $id;
                unset($_SESSION['otp_verification']);
                header("Location: reset_password.php");
                exit();
            } else {
                $_SESSION['otp_error'] = "Invalid OTP. Please try again.";
                header("Location: verify.php");
                exit();
            }
        } else {
            // Handle registration/login verification
            if ($type === 'registration') {
                $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ? AND is_verified = 0");
                $stmt->bind_param("s", $phone);
            } else { // login
                $id = $verification['id'];
                $stmt = $conn->prepare("SELECT otp_hash, otp_expiry FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
            }
            
            $stmt->execute();
            $stmt->bind_result($id, $otp_hash, $otp_expiry);
            $stmt->fetch();
            $stmt->close();

            // Check if OTP is expired
            if (strtotime($otp_expiry) < time()) {
                $_SESSION['otp_error'] = "OTP has expired. Please request a new one.";
                header("Location: verify.php");
                exit();
            } elseif ($otp_hash && password_verify($otp, $otp_hash)) {
                if ($type === 'registration') {
                    // Mark account as verified
                    $update = $conn->prepare("UPDATE users SET is_verified = 1, otp_hash = NULL, otp_expiry = NULL WHERE id = ?");
                    $update->bind_param("i", $id);
                    $update->execute();
                    $update->close();
                }

                $_SESSION['user_id'] = $id;
                unset($_SESSION['otp_verification']);
                header("Location: ../../index.php");
                exit();
            } else {
                $_SESSION['otp_error'] = "Invalid OTP. Please try again.";
                header("Location: verify.php");
                exit();
            }
        }
    }
}

$pageTitle = 'Verify OTP';
$isAuthPage = true;
include '../../includes/header.php';

// Get any error message from session
$error = isset($_SESSION['otp_error']) ? $_SESSION['otp_error'] : '';
$error_type = '';
if ($error) {
    if (strpos($error, 'expired') !== false) {
        $error_type = 'warning';
    } elseif (strpos($error, 'Invalid') !== false) {
        $error_type = 'danger';
    }
    unset($_SESSION['otp_error']);
}

function maskPhone($phone) {
    // Example: +639934253236 -> +6399****3236
    if (preg_match('/^\+63\d{10}$/', $phone)) {
        $first = substr($phone, 0, 5); // +6399
        $last = substr($phone, -4);    // 3236
        return $first . '****' . $last;
    }
    return $phone; // fallback if not valid format
}

$maskedPhone = maskPhone($phone);
?>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">OTP Verification</h2>
        <p class="text-muted">
            <?php 
            if ($type === 'registration') {
                echo 'Registration confirmation for ';
            } elseif ($type === 'login') {
                echo 'Login confirmation for ';
            } else {
                echo 'Password reset confirmation for ';
            }
            ?>
            <strong><?= $maskedPhone ?></strong>
        </p>
        <div class="text-center mt-3">
            <p id="countdown" class="text-muted">OTP expires in: <span id="timer"><?= sprintf("%02d:%02d", floor($remaining_time/60), $remaining_time%60) ?></span></p>
        </div>
    </div>
    
    <!-- Alert container -->
    <div id="alert-container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-<?= $error_type ?>" id="status-alert">
                <?= $error ?>
            </div>
        <?php endif; ?>
    </div>
    
    <form method="POST" id="verifyForm">
        <div class="mb-3 d-flex justify-content-center">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" name="otp[]" class="form-control otp-input mx-1 text-center" 
                       maxlength="1" required autocomplete="off"
                       <?= $i === 0 ? 'autofocus' : '' ?>
                       <?= $is_expired ? 'disabled' : '' ?>>
            <?php endfor; ?>
        </div>
    </form>
    
    <p class="text-center mt-3">Didn't receive a code? <a href="#" id="resend-otp">Resend OTP</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // OTP input auto focus
    const otpInputs = document.querySelectorAll('.otp-input');
    const alertContainer = document.getElementById('alert-container');
    let isExpired = <?= $is_expired ? 'true' : 'false' ?>;
    let countdownInterval;
    
    // Function to handle OTP input
    function setupOtpInputs() {
        otpInputs.forEach((input, index) => {
            // Clear any existing event listeners
            input.replaceWith(input.cloneNode(true));
        });
        
        // Get fresh references after cloning
        const freshInputs = document.querySelectorAll('.otp-input');
        
        freshInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                // Allow only numbers
                input.value = input.value.replace(/\D/g, '');
                
                if (input.value.length === 1 && index < freshInputs.length - 1) {
                    freshInputs[index + 1].focus();
                }
                
                // Auto-submit when all fields are filled
                const allFilled = Array.from(freshInputs).every(input => input.value.length === 1);
                if (allFilled && !isExpired) {
                    document.getElementById('verifyForm').submit();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                // Handle backspace
                if (e.key === 'Backspace' && input.value.length === 0 && index > 0) {
                    freshInputs[index - 1].focus();
                }
                
                // Handle paste
                if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
                    setTimeout(() => {
                        const pastedData = input.value;
                        if (pastedData.length === 6) {
                            for (let i = 0; i < 6; i++) {
                                if (freshInputs[i]) {
                                    freshInputs[i].value = pastedData[i] || '';
                                }
                            }
                            if (!isExpired) {
                                document.getElementById('verifyForm').submit();
                            }
                        }
                    }, 10);
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                if (pastedData.length === 6) {
                    for (let i = 0; i < 6; i++) {
                        if (freshInputs[i]) {
                            freshInputs[i].value = pastedData[i] || '';
                        }
                    }
                    if (!isExpired) {
                        document.getElementById('verifyForm').submit();
                    }
                }
            });
        });
    }

    // Only enable auto-submit if OTP is not expired
    if (!isExpired) {
        setupOtpInputs();
    }

    // Countdown timer for OTP expiry
    let timeLeft = <?= $remaining_time ?>;
    const timerElement = document.getElementById('timer');
    
    function startCountdown() {
        if (countdownInterval) clearInterval(countdownInterval);
        
        countdownInterval = setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                timerElement.textContent = "00:00";
                timerElement.classList.add('text-danger');
                isExpired = true;
                showExpiredMessage();
            } else {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Change color when under 1 minute
                if (timeLeft < 60) {
                    timerElement.classList.add('text-danger');
                }
            }
        }, 1000);
    }
    
    function showExpiredMessage() {
    // Grab the latest inputs
    const freshInputs = document.querySelectorAll('.otp-input');
    
    freshInputs.forEach(input => {
        input.disabled = true;
    });
    
    // Show expired message
    showAlert('OTP has expired. Please request a new one.', 'warning');
}

    
    function showAlert(message, type) {
        alertContainer.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        alertContainer.appendChild(alert);
    }
    
    function resetForm() {
    const freshInputs = document.querySelectorAll('.otp-input');
    
    freshInputs.forEach(input => {
        input.disabled = false;
        input.value = '';
    });
    
    setupOtpInputs();
    if (freshInputs[0]) freshInputs[0].focus();

    timeLeft = 300; // or dynamic from server
    timerElement.textContent = '05:00';
    timerElement.classList.remove('text-danger');
    isExpired = false;

    startCountdown();
}

    // Start countdown if not expired
    if (timeLeft > 0) {
        startCountdown();
    } else {
        showExpiredMessage();
    }

    // Resend OTP functionality
    document.getElementById('resend-otp').addEventListener('click', async (e) => {
        e.preventDefault();
        
        try {
            const formData = new FormData();
            formData.append('action', 'resend_otp');
            
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                // Show success message
                showAlert('OTP has been resent successfully!', 'success');
                
                // Reset the form
                resetForm();
            } else {
                showAlert('Failed to resend OTP: ' + result.message, 'danger');
            }
        } catch (error) {
            showAlert('Error: ' + error.message, 'danger');
        }
    });
});
</script>

<style>
.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.otp-input:disabled {
    background-color: #e9ecef;
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<?php include '../../includes/footer.php'; ?>