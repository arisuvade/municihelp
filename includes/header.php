<?php
// Detect the current page context
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isAuthPage = str_contains($currentPath, '/includes/auth/');
$isAdminPage = str_contains($currentPath, '/admin/');
$isVMRJPage = str_contains($currentPath, '/vm_rj_assistance/');
$isRootPage = !$isAuthPage && !$isAdminPage && !$isVMRJPage;

// base path for user
if ($isAuthPage) {
    $basePath = '../../'; // includes/auth/
} elseif ($isAdminPage || $isVMRJPage) {
    $basePath = '../'; // /admin or /vm_rj_assistance
} else {
    $basePath = ''; // /..
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuniciHelp - <?= $pageTitle ?? 'Home' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="<?= $basePath ?>assets/css/styles.css" rel="stylesheet">
    <style>
    :root {
        --munici-green-dark: #1B5E20;
        --munici-green-light: #4CAF50;
    }
    
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
    
    .main-container {
        display: flex;
        flex: 1;
    }
    
    .vertical-nav {
        width: 220px;
        background-color: var(--munici-green-dark);
        padding: 15px 0;
        flex-shrink: 0;
    }
    
    .vertical-nav .nav-link {
        color: white;
        padding: 10px 15px;
        margin: 2px 0;
        border-radius: 4px;
        display: flex;
        align-items: center;
        transition: all 0.2s;
    }
    
    .vertical-nav .nav-link:hover, 
    .vertical-nav .nav-link.active {
        background-color: var(--munici-green-light);
        color: var(--munici-green-dark);
    }
    
    .vertical-nav .nav-link.active span {
        font-weight: bold;
    }
    
    .main-content {
        flex: 1;
        padding: 20px;
        width: calc(100% - 220px);
    }
    
    @media (max-width: 992px) {
        .vertical-nav {
            width: 60px;
        }
        .nav-text {
            display: none;
        }
        .main-content {
            width: calc(100% - 60px);
        }
    }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-munici-green">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= isset($_SESSION['admin_id']) ? $basePath.'admin/dashboard.php' : $basePath.'index.php' ?>">
                <img src="<?= $basePath ?>assets/images/logo-pulilan.png" alt="Pulilan Logo" height="40">
                MuniciHelp
            </a>
            <?php if(!$isAuthPage): ?>
                <div class="d-flex align-items-center">
                    <?php if(isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])): ?>
                        <a href="<?= $basePath ?>includes/auth/logout.php" class="btn btn-outline-light btn-sm logout-link">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if((isset($_SESSION['admin_id']) || (isset($_SESSION['user_id']) && !$isAuthPage))): ?>
    <div class="main-container">
        <nav class="vertical-nav">
            <ul class="nav flex-column px-2">
                <?php if(isset($_SESSION['admin_id'])): ?>
                    <!-- admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'pending.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>admin/pending.php">
                            <i class="bi bi-hourglass-split"></i>
                            <span class="nav-text ms-2">Pending</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'approved.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>admin/approved.php">
                            <i class="bi bi-check-circle"></i>
                            <span class="nav-text ms-2">Approved</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>
                <?php elseif(isset($_SESSION['user_id'])): ?>
                    <!-- user -->
                    <li class="nav-item">
                        <a class="nav-link <?= ($isRootPage && $currentPage == 'index.php') ? 'active' : '' ?>" 
                           href="<?= $basePath ?>index.php">
                            <i class="bi bi-house"></i>
                            <span class="nav-text ms-2">Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $isVMRJPage ? 'active' : '' ?>" 
                           href="<?= $basePath ?>vm_rj_assistance/index.php">
                            <i class="bi bi-wallet2"></i>
                            <span class="nav-text ms-2">VM RJ Assistance</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <main class="main-content">
<?php else: ?>
    <main class="container my-5">
<?php endif; ?>