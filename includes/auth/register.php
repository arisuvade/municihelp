<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPhone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // auto add +63
    $phone = $inputPhone;
    if (strpos($phone, '+63') !== 0) {
        // Remove any leading 0 or 63
        $phone = ltrim($phone, '0');
        $phone = ltrim($phone, '63');
        $phone = '+63' . $phone;
    }

    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit Philippine mobile number (e.g., 9123456789)";
    }
    // check if the password is 8+ characters
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $otp = rand(100000, 999999);
        $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
        $otp_expiry = date('Y-m-d H:i:s', time() + 300);

        // check if the number already exist
        $check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $check->bind_param("s", $phone);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Ang numero ng telepono ay naka-rehistro na.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (phone, password_hash, otp_hash, otp_expiry) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $phone, $password_hash, $otp_hash, $otp_expiry);
            
            if ($stmt->execute()) {
                // send otp via sms
                require '../../includes/send_sms.php';
                $message = "Your MuniciHelp verification code is: $otp";
                $phone_numbers = [$phone];
                
                $smsResponse = sendSMS($message, $phone_numbers);
                
                if ($smsResponse !== false) {
                    $_SESSION['otp_verification'] = [
                        'phone' => $phone,
                        'type' => 'registration'
                    ];
                    header("Location: verify.php");
                    exit();
                } else {
                    $error = "Hindi naipadala ang OTP sa SMS. Mangyaring subukan ulit mamaya.";
                }
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

$pageTitle = 'Register';
$isAuthPage = true;
include '../../includes/header.php';
?>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">Gumawa ng Account</h2>
        <p class="text-muted">Municipal Assistance System</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" onsubmit="return validateForm()">
        <div class="mb-3">
            <label for="phone" class="form-label">Numero ng Telepono</label>
            <div class="input-group">
                <span class="input-group-text">+63</span>
                <input type="tel" class="form-control" id="phone" name="phone" required 
                       placeholder="9123456789" pattern="\d{10}" maxlength="10"
                       title="Please enter your 10-digit mobile number (e.g., 9123456789)">
            </div>
            <small class="form-text text-muted">Ilagay ang iyong 10-digit na mobile number (hal. 9123456789)</small>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <small id="passwordHelp" class="form-text text-muted">Ang password ay dapat may hindi bababa sa 8 karakter.</small>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label"> Kumpirmahin ang Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-munici-green w-100">Register</button>
    </form>
    
    <p class="text-center mt-3">May account ka na? <a href="login.php">Mag-login dito.</a></p>
</div>

<script>
    // hide or unhide pass
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });

    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
        const confirmPasswordInput = document.getElementById('confirm_password');
        const icon = this.querySelector('i');
        if (confirmPasswordInput.type === 'password') {
            confirmPasswordInput.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            confirmPasswordInput.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });

    // client side validation
    function validateForm() {
        const phoneInput = document.getElementById('phone');
        const phone = phoneInput.value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // validate numbers if it is exactly 10digits
        if (!/^\d{10}$/.test(phone)) {
            alert('Please enter a valid 10-digit mobile number (e.g., 9123456789)');
            phoneInput.focus();
            return false;
        }
        
        if (password.length < 8) {
            alert('Password must be at least 8 characters.');
            return false;
        }
        
        if (password !== confirmPassword) {
            alert('Passwords do not match.');
            return false;
        }
        
        return true;
    }
</script>

<?php include '../../includes/footer.php'; ?> 