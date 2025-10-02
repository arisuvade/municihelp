<?php
session_start();
include '../db.php';

// Redirect if already logged in as a user
if (isset($_SESSION['user_id'])) {
    header("Location: ../../user/dashboard.php");
    exit();
}

// Redirect if already logged in as any admin
if (
    isset($_SESSION['mayor_superadmin_id']) ||
    isset($_SESSION['mswd_admin_id']) ||
    isset($_SESSION['mayor_admin_id']) ||
    isset($_SESSION['pwd_admin_id']) ||
    isset($_SESSION['animal_admin_id']) ||
    isset($_SESSION['pound_admin_id']) ||
    isset($_SESSION['vice_mayor_superadmin_id']) ||
    isset($_SESSION['assistance_admin_id']) ||
    isset($_SESSION['ambulance_admin_id']) ||
    isset($_SESSION['barangay_admin_id'])  // Added barangay admin
) {
    if (isset($_SESSION['admin_department_path'])) {
        header("Location: ../../" . $_SESSION['admin_department_path']);
    } else {
        header("Location: ../../admin/dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPhone = trim($_POST['phone']);
    $password = $_POST['password'];

    // Format phone number to +63 format
    $phone = $inputPhone;
    if (strpos($phone, '+63') !== 0) {
        $phone = ltrim($phone, '0');
        $phone = ltrim($phone, '63');
        $phone = '+63' . $phone;
    }

    if (!preg_match('/^\+63\d{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit Philippine mobile number (e.g., 9123456789)";
    } else {
        // === ADMIN LOGIN CHECK ===
        $admin_stmt = $conn->prepare("
            SELECT a.id, a.password, a.name, a.department_id, d.path 
            FROM admins a 
            JOIN departments d ON a.department_id = d.id 
            WHERE a.phone = ?
        ");
        if (!$admin_stmt) die("DB error: " . $conn->error);

        $admin_stmt->bind_param("s", $phone);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();

        if ($admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();

            if (password_verify($password, $admin['password'])) {
                // Common admin session vars
                $_SESSION['admin_department_id'] = $admin['department_id'];
                $_SESSION['admin_department_path'] = $admin['path'];
                $_SESSION['admin_phone'] = $phone;
                $_SESSION['admin_name'] = $admin['name'];

                // Role-specific session assignment
                switch ($admin['department_id']) {
                    case 1: // Mayor Superadmin
                        $_SESSION['mayor_superadmin_id'] = $admin['id'];
                        break;
                    case 2: // MSWD
                        $_SESSION['mswd_admin_id'] = $admin['id'];
                        break;
                    case 3: // Mayor Admin
                        $_SESSION['mayor_admin_id'] = $admin['id'];
                        break;
                    case 4: // PWD
                        $_SESSION['pwd_admin_id'] = $admin['id'];
                        break;
                    case 5: // Animal Control
                        $_SESSION['animal_admin_id'] = $admin['id'];
                        break;
                    case 6: // Pound Admin
                        $_SESSION['pound_admin_id'] = $admin['id'];
                        break;
                    case 7: // Vice Mayor Superadmin
                        $_SESSION['vice_mayor_superadmin_id'] = $admin['id'];
                        break;
                    case 8: // Assistance
                        $_SESSION['assistance_admin_id'] = $admin['id'];
                        break;
                    case 9: // Ambulance
                        $_SESSION['ambulance_admin_id'] = $admin['id'];
                        break;
                    default: // Barangay Admins (IDs 17-35)
                        if ($admin['department_id'] >= 17 && $admin['department_id'] <= 35) {
                            $_SESSION['barangay_admin_id'] = $admin['id'];
                        }
                        break;
                }

                $admin_stmt->close();
                header("Location: ../../" . $admin['path']);
                exit();
            } else {
                $error = "Invalid phone number or password";
            }
        }
        $admin_stmt->close();

        // === USER LOGIN CHECK ===
        $user_stmt = $conn->prepare("SELECT id, password_hash, is_verified, phone, is_temp_password FROM users WHERE phone = ?");
        if (!$user_stmt) die("DB error: " . $conn->error);

        $user_stmt->bind_param("s", $phone);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
                if (!$user['is_verified']) {
                    $_SESSION['phone'] = $phone;
                    $user_stmt->close();
                    header("Location: verify.php");
                    exit();
                }

                // === Check if still using temporary password ===
                if ($user['is_temp_password'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['force_password_change'] = true;
                    $user_stmt->close();
                    header("Location: ../../user/change_password.php");
                    exit();
                }

                // === Normal login ===
                $_SESSION['user_id'] = $user['id'];
                $user_stmt->close();
                header("Location: ../../index.php");
                exit();
            } else {
                $error = "Invalid phone number or password";
            }
        } else {
            $error = "Invalid phone number or password";
        }
        $user_stmt->close();
    }
}

$pageTitle = 'Login';
$isAuthPage = true;
include '../../includes/header.php';
?>

<link rel="icon" href="../../favicon.ico" type="image/x-icon">
<style>
.auth-container {
    max-width: 450px; 
    margin: 2rem auto;
    padding: 2rem;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-munici-green {
    background-color: #0E3B85;
    border-color: #0E3B85;
    color: white;
}

.btn-munici-green:hover {
    background-color: #0a2c6e;
    border-color: #0a2c6e;
    color: white;
}

.text-munici-green {
    color: #0E3B85;
}

@media (max-width: 767.98px) {
    .auth-container {
        margin: 1rem auto;
        padding: 1.5rem;
        box-shadow: none;
    }
}
</style>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3 text-munici-green">MuniciHelp Login</h2>
        <p class="text-muted">Municipal Assistance Portal</p>
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
            <small class="form-text text-muted">Enter your 10-digit mobile number (e.g. 9123456789)</small>
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
        <p class="text-center mt-3 small text-muted">Forgot password? <a href="forgot_password.php" class="text-munici-green">Reset here</a></p>
    </form>

    <p class="text-center mt-3 small text-muted">
        No account yet? <br>
        <a href="register.php" class="text-munici-green">Visit your barangay for verification</a>
    </p>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pw = document.getElementById('password');
        const icon = this.querySelector('i');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
    });
</script>

<?php include '../../includes/footer.php'; ?>