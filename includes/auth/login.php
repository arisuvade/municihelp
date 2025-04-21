<?php
session_start();
include '../db.php';

// check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (isset($_SESSION['admin_id'])) {
    header("Location: ../admin/dashboard.php");
    exit();
}

// admin testing only!!!!!!!!!!!!!!!!!!!!!!!!!!!
$hardcoded_admins = [
    '+639999999991' => [
        'password' => 'a',
        'section' => 'Financial Assistance',
        'id' => 1
    ],
    '+639999999992' => [
        'password' => 'r',
        'section' => 'Request Processing',
        'id' => 2
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPhone = trim($_POST['phone']);
    $password = $_POST['password'];

    // auto add +63
    $phone = $inputPhone;
    if (strpos($phone, '+63') !== 0) {
        $phone = ltrim($phone, '0');
        $phone = ltrim($phone, '63');
        $phone = '+63' . $phone;
    }

    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit Philippine mobile number (e.g., 9123456789)";
    } else {
        // check if it is the testing admin acc
        if (isset($hardcoded_admins[$phone])) {
            $admin = $hardcoded_admins[$phone];
            
            if ($password === $admin['password']) {
                // no otp for testing admin
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_phone'] = $phone;
                $_SESSION['admin_section'] = $admin['section'];
                header("Location: ../../admin/dashboard.php");
                exit();
            }
        }

        $admin_stmt = $conn->prepare("SELECT id, password, section FROM admins WHERE phone = ?");
        if ($admin_stmt === false) {
            die("Error preparing admin statement: " . $conn->error);
        }
        
        $admin_stmt->bind_param("s", $phone);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        
        if ($admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            
            if ($password === $admin['password']) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_phone'] = $phone;
                $_SESSION['admin_section'] = $admin['section'];
                $admin_stmt->close();
                header("Location: ../../admin/dashboard.php");
                exit();
            }
        }
        $admin_stmt->close();

        // user with otp
        $user_stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM users WHERE phone = ?");
        if ($user_stmt === false) {
            die("Error preparing user statement: " . $conn->error);
        }
        
        $user_stmt->bind_param("s", $phone);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                if (!$user['is_verified']) {
                    // account still not verified
                    $_SESSION['phone'] = $phone;
                    $user_stmt->close();
                    header("Location: verify.php");
                    exit();
                }

                // generate otp for login
                $otp = rand(100000, 999999);
                $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
                $otp_expiry = date('Y-m-d H:i:s', time() + 300);

                $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE id = ?");
                if ($update === false) {
                    die("Error preparing update statement: " . $conn->error);
                }
                
                $update->bind_param("ssi", $otp_hash, $otp_expiry, $user['id']);
                
                if ($update->execute()) {
                    // Send OTP via SMS
                    require '../../includes/send_sms.php';
                    $message = "Your MuniciHelp verification code is: $otp";
                    $phone_numbers = [$phone];
                    
                    $smsResponse = sendSMS($message, $phone_numbers);
                    
                    if ($smsResponse !== false) {
                        $_SESSION['otp_verification'] = [
                            'id' => $user['id'], 
                            'phone' => $phone, 
                            'type' => 'login'
                        ];
                        $update->close();
                        $user_stmt->close();
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
                $error = "Di-wastong numero ng telepono o password.";
            }
        } else {
            $error = "Di-wastong numero ng telepono o password.";
        }
        $user_stmt->close();
    }
}

$pageTitle = 'Login';
$isAuthPage = true;
include '../../includes/header.php';
?>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">MuniciHelp Login</h2>
        <p class="text-muted">Municipal Assistance Portal</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
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
                <input type="password" class="form-control" id="password" name="password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-munici-green w-100">Login</button>
        <p class="text-center mt-3">Nakalimutan ang password? <a href="forgot_password.php">I-reset dito</a></p>
    </form>
    
    <p class="text-center mt-3">Kailangan mo ng account? <a href="register.php">Magrehistro dito</a></p>
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
</script>

<?php include '../../includes/footer.php'; ?>