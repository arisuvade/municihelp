<?php
session_start();
require_once '../includes/db.php';

// User must be logged in AND have is_temp_password flag set to 1
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user has temporary password flag set
$user_query = $conn->prepare("SELECT phone, is_temp_password FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// If user doesn't have temporary password flag, redirect to dashboard
if ($user_data['is_temp_password'] != 1) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    }
    // Check password length
    else if (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    }
    else {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in database and reset the temp password flag
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, is_temp_password = 0 WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Remove the force password change flag and redirect to dashboard
            unset($_SESSION['force_password_change']);
            $stmt->close();
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}

$pageTitle = "Change Password";
$isAuthPage = true;
include '../includes/header_simple.php';
?>

<link rel="icon" href="../../favicon.ico" type="image/x-icon">
<style>
.walkin-info-container {
    font-size: 1rem;
    padding-left: 0;
    padding-right: 0;
}

.walkin-info-container .accordion-button {
    font-size: 1.1rem;
    padding: 1rem;
    font-weight: 600;
}
.walkin-info-container .accordion-body {
    padding: 1.25rem;
}
.walkin-info-container .list-group-item {
    font-size: 0.95rem;
    padding: 0.75rem 1.25rem;
}
.faq-item {
    border-left: 3px solid var(--munici-green);
    padding-left: 1.25rem;
    margin-bottom: 1.25rem;
}
.faq-item h6 {
    font-weight: 600;
    color: var(--munici-green);
}
.location-box {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1.25rem;
    margin-top: 1.5rem;
    border: 1px solid #e9ecef;
}
.walkin-header {
    padding-left: 0;
    padding-right: 0;
    margin-bottom: 1.5rem;
}
.accordion-item {
    margin-bottom: 0.75rem;
    border: 1px solid rgba(0,0,0,0.125);
    border-radius: 0.5rem;
}
.accordion-button:not(.collapsed) {
    background-color: var(--munici-green);
    color: white;
}
.accordion-button:not(.collapsed)::after {
    filter: brightness(0) invert(1);
}
.sub-accordion .accordion-item {
    border: 1px solid #e9ecef;
    margin-bottom: 0.5rem;
}
.sub-accordion .accordion-button {
    font-size: 1rem;
    padding: 0.875rem;
    background-color: #f8f9fa;
}
.sub-accordion .accordion-button:not(.collapsed) {
    background-color: #e9ecef;
    color: var(--munici-green);
}
.advertisement-box {
    background: linear-gradient(135deg, var(--munici-green), #0E3B85);
    color: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 2rem;
}

/* Slim login form styles */
.auth-container {
    max-width: 450px; 
    margin: 0 auto;
    padding: 2rem;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    min-height: 600px;
}

.form-control {
    max-width: 100%;
    padding: 0.5rem 0.75rem;
}

/* Responsive adjustments */
@media (min-width: 768px) {
    .walkin-info-container, .walkin-header {
        padding-left: 2rem;
        padding-right: 2rem;
    }
    
    .container-fluid {
        padding-left: 2rem;
        padding-right: 2rem;
    }
    
    .min-vh-100 {
        align-items: center;
    }
    
    .auth-container {
        margin-top: 0;
        margin-bottom: 0;
    }
}

@media (min-width: 992px) {
    .walkin-info-container, .walkin-header {
        padding-left: 3rem;
        padding-right: 3rem;
    }
    
    .container-fluid {
        padding-left: 3rem;
        padding-right: 3rem;
    }
}

@media (min-width: 1200px) {
    .walkin-info-container, .walkin-header {
        padding-left: 4rem;
        padding-right: 4rem;
    }
    
    .container-fluid {
        padding-left: 4rem;
        padding-right: 4rem;
    }
}

@media (min-width: 1400px) {
    .walkin-info-container, .walkin-header {
        padding-left: 5rem;
        padding-right: 5rem;
    }
    
    .container-fluid {
        padding-left: 5rem;
        padding-right: 5rem;
    }
}

/* Mobile specific styles */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .min-vh-100 {
        min-height: 100vh !important;
    }
    
    .auth-container {
        margin: 1rem auto;
        padding: 1.5rem;
    }
    
    .advertisement-box {
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
    }
}

/* Center the form on the right side */
@media (min-width: 768px) {
    .col-md-6.align-items-center {
        display: flex;
        justify-content: center;
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
    
    .auth-container {
        width: 100%;
        max-width: 400px;
    }
    
    .bg-light {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
}

/* Improved layout for larger screens */
@media (min-width: 992px) {
    .row.min-vh-100 {
        gap: 2rem;
    }
    
    .col-md-6 {
        flex: 0 0 calc(50% - 1rem);
        max-width: calc(50% - 1rem);
    }
}

/* Even more balanced layout for very large screens */
@media (min-width: 1400px) {
    .row.min-vh-100 {
        gap: 3rem;
    }
    
    .col-md-6 {
        flex: 0 0 calc(50% - 1.5rem);
        max-width: calc(50% - 1.5rem);
    }
}
</style>

<div class="container-fluid">
    <div class="row min-vh-100">
        <!-- Right Side: Password Change Form (Moved to top on mobile) -->
        <div class="col-md-6 order-md-2 align-items-center justify-content-center p-4">
            <div class="auth-container">
                <div class="text-center mb-4">
                    <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
                    <h2 class="mt-3 text-munici-green">Change Password</h2>
                    <p class="text-muted">Set a new secure password for your account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Re-enter your new password to confirm.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-munici-green w-100">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Left Side: Information (Moved to bottom on mobile) -->
        <div class="col-md-6 order-md-1 bg-light p-4 d-flex flex-column">
            <div class="walkin-header">
                <h2 class="text-munici-green">Password Change Required</h2>
                <p class="text-muted">For security reasons, please change your temporary password</p>
            </div>

            <div class="walkin-info-container flex-grow-1">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Security Notice</h5>
                    <p>Your account was created with a temporary password. To protect your account, please set a new secure password.</p>
                </div>

                <div class="mt-4">
                    <h4 class="text-munici-green mb-3">Password Guidelines</h4>
                    
                    <div class="faq-item">
                        <h6>Create a strong password:</h6>
                        <ul>
                            <li>Use at least 8 characters</li>
                            <li>Include letters, numbers, and symbols</li>
                            <li>Avoid using personal information</li>
                        </ul>
                    </div>
                </div>

                <div class="location-box">
                    <h5 class="text-munici-green mb-3">
                        <i class="fas fa-shield-alt me-2"></i>Account Security
                    </h5>
                    <p>Your password helps protect your personal information and ensures only you can access your MuniciHelp account.</p>
                </div>

                <!-- Advertisement for Online Services -->
                <div class="advertisement-box">
                    <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Secure Your Account</h5>
                    <p class="mb-3">A strong password protects your personal information and ensures the security of your MuniciHelp account.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    document.getElementById('toggleNewPassword').addEventListener('click', function() {
        const pw = document.getElementById('new_password');
        const icon = this.querySelector('i');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
    });

    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
        const pw = document.getElementById('confirm_password');
        const icon = this.querySelector('i');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
    });

    // Proper accordion functionality - each accordion works independently
    document.querySelectorAll('.accordion-button').forEach(button => {
        button.addEventListener('click', function() {
            // Get the target collapse element
            const target = this.getAttribute('data-bs-target');
            const collapseElement = document.querySelector(target);
            
            // Create a Bootstrap Collapse instance
            const bsCollapse = new bootstrap.Collapse(collapseElement, {
                toggle: true
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>