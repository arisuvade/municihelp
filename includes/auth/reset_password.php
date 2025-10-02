<?php
session_start();
include '../db.php';

if (!isset($_SESSION['password_reset_user'])) {
    header("Location: login.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['password_reset_user'];

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $stmt = null;
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if (!$stmt) throw new Exception($conn->error);
            $stmt->bind_param("si", $password_hash, $user_id);
            
            if ($stmt->execute()) {
                // Password updated successfully
                $_SESSION['user_id'] = $user_id;
                unset($_SESSION['password_reset_user']);
                $_SESSION['success_message'] = "Password updated successfully.";
                header("Location: ../../index.php");
                exit();
            } else {
                $error = "Error updating password. Please try again.";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        } finally {
            if ($stmt) $stmt->close();
        }
    }
}

$pageTitle = 'Reset Password';
$isAuthPage = true;
include '../../includes/header.php';
?>

<link rel="icon" href="../../favicon.ico" type="image/x-icon">

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">Reset Password</h2>
        <p class="text-muted">Create a new password for your account:</p>
    </div>
    
    <div id="errorContainer">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger floating-alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>
    
    <form method="POST" id="resetForm">
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <small class="form-text text-muted">Password must be at least 8 characters.</small>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-munici-green w-100">Reset Password</button>
    </form>
</div>

<script>
    // Toggle password visibility
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

    // Client side validation
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password.length < 8) {
            e.preventDefault();
            showError('Password must be at least 8 characters.');
            return false;
        }
        
        if (password !== confirmPassword) {
            e.preventDefault();
            showError('Passwords do not match.');
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