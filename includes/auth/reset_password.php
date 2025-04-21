<?php
session_start();
include '../db.php';

if (!isset($_SESSION['password_reset_user'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['password_reset_user'];

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // password updated successfully
            $_SESSION['user_id'] = $user_id;
            unset($_SESSION['password_reset_user']);
            $_SESSION['success_message'] = "Password updated successfully.";
            header("Location: ../../index.php");
            exit();
        } else {
            $error = "Error updating password. Please try again.";
        }
        $stmt->close();
    }
}

$pageTitle = 'Reset Password';
$isAuthPage = true;
include '../../includes/header.php';
?>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">Reset Password</h2>
        <p class="text-muted">Gumawa ng bagong password para sa iyong account:</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label for="password" class="form-label">Bagong Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <small class="form-text text-muted">Ang password ay dapat may hindi bababa sa 8 karakter.</small>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Kumpirmahin ang Bagong Password</label>
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
</script>

<?php include '../../includes/footer.php'; ?>