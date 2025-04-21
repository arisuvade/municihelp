<?php
session_start();
$pageTitle = 'Home';
include 'includes/header.php';
?>

<main class="container my-5">
    <div class="text-center">
        <img src="assets/images/logo-pulilan.png" alt="Pulilan Logo" class="img-fluid mb-4 rounded" style="max-width: 300px;">
        <h1>Welcome to MuniciHelp</h1>
        <p class="lead">Pulilan Municipal Assistance System</p>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="mt-4">
                <a href="vm_rj_assistance/index.php" class="btn btn-munici-green btn-lg">Vice Mayor RJ Peralta Assistance</a>
            </div>
        <?php else: ?>
            <div class="mt-4">
                <a href="includes/auth/login.php" class="btn btn-munici-green btn-lg me-2">Login</a>
                <a href="includes/auth/register.php" class="btn btn-outline-munici-green btn-lg">Register</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>