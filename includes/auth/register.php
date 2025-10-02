<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$pageTitle = 'Barangay Verification Process';
$isAuthPage = true;
include '../../includes/header.php';
?>

<link rel="icon" href="../../favicon.ico" type="image/x-icon">

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3" style="color: var(--munici-green-dark)">Barangay Account Verification</h2>
        <p class="text-muted">Municipal Assistance System</p>
    </div>
    
    <div class="card border-munici-green">
        <div class="card-body">
            <div class="alert alert-munici" style="background-color: var(--munici-green-light-bg); border-color: var(--munici-green-light); color: var(--munici-green-dark)">
                <h4 class="alert-heading"><i class="bi bi-building me-2"></i>In-Person Verification Required</h4>
                <p>To access municipal services, please complete identity verification at your barangay hall.</p>
            </div>
            
            <div class="verification-steps">
                <h5 class="mb-3" style="color: var(--munici-green-dark)"><i class="bi bi-list-check me-2"></i>Verification Process:</h5>
                
                <div class="step mb-3">
                    <div class="step-number" style="background: var(--munici-green)">1</div>
                    <div class="step-content">
                        <strong>Visit your barangay hall</strong>
                        <p class="mb-0 text-muted">Bring the required documents during office hours</p>
                    </div>
                </div>
                
                <div class="step mb-3">
                    <div class="step-number" style="background: var(--munici-green)">2</div>
                    <div class="step-content">
                        <strong>Present your documents</strong>
                        <ul class="mt-2 mb-0">
                            <li>Original valid government ID</li>
                            <li>Active mobile number</li>
                        </ul>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number" style="background: var(--munici-green)">3</div>
                    <div class="step-content">
                        <strong>Receive your credentials</strong>
                        <p class="mb-0 text-muted">Barangay staff will activate your account</p>
                    </div>
                </div>
            </div>
            
            <div class="office-hours mt-4 p-3 rounded" style="background-color: var(--munici-green-light-bg); border-left: 4px solid var(--munici-green)">
                <h6 style="color: var(--munici-green-dark)"><i class="bi bi-clock me-2"></i>Barangay Office Hours:</h6>
                <p class="mb-1">Monday-Friday: 8:00 AM - 5:00 PM</p>
                <p class="mb-0">Closed on weekends and holidays</p>
            </div>
            
            <a href="login.php" class="btn btn-munici-green w-100 mt-4">
                <i class="bi bi-arrow-left me-2"></i>Return to Login
            </a>
        </div>
    </div>
</div>

<style>
.verification-steps {
    border-left: 3px solid var(--munici-green);
    padding-left: 1rem;
}
.step {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.step-number {
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
    margin-top: 2px;
}
.step-content {
    flex-grow: 1;
}
</style>

<?php include '../../includes/footer.php'; ?>