<?php
// Detect the current page context
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Determine base path
$isAuthPage = str_contains($currentPath, '/includes/auth/');
if ($isAuthPage) {
    $basePath = '../../';
} else {
    $basePath = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Home' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="<?= $basePath ?>../../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --munici-green-dark: #0E3B85;
        --munici-green-light: #2C80C6;
        --munici-green: #42A5F5;
        --munici-green-light-bg: #E3F2FD;
        --pending: #FFC107;
        --approved: #28A745;
        --completed: #4361ee;
        --emerggency: #D32F2F;
        --light-color: #f8f9fa;
        --dark-color: #212529;
    }
    
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background-color: #f5f7fb;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        transition: all 0.3s ease;
    }

    .vertical-nav .nav-link {
        color: rgba(255, 255, 255, 0.9);
        padding: 10px 15px;
        margin: 2px 0;
        border-radius: 4px;
        display: flex;
        align-items: center;
        transition: all 0.2s;
        font-weight: 500;
    }

    .vertical-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateX(3px);
    }

    .vertical-nav .nav-link.active {
        background-color: white !important;
        color: var(--munici-green-dark) !important;
        font-weight: 600;
        transform: translateX(5px);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .vertical-nav .nav-link.active i {
        color: var(--munici-green-dark) !important;
    }

    .vertical-nav .nav-item ul .nav-link.active {
        background-color: rgba(255, 255, 255, 0.9) !important;
        color: var(--munici-green-dark) !important;
        font-weight: 500;
        border-left: 3px solid var(--munici-green-light);
        transform: none;
    }

    .vertical-nav .nav-item ul .nav-link.active i {
        color: var(--munici-green-dark) !important;
    }

    .vertical-nav .nav-link i {
        width: 24px;
        text-align: center;
        font-size: 1.1rem;
        transition: all 0.2s;
    }

    .vertical-nav .nav-link.active i {
        transform: scale(1.1);
    }

    .vertical-nav .nav-link .nav-text {
        transition: all 0.2s;
        margin-left: 10px;
    }

    .vertical-nav .nav-link.active .nav-text {
        letter-spacing: 0.3px;
    }
    
    .main-content {
        flex: 1;
        padding: 20px;
        background-color: #f5f7fb;
    }
    
    .mobile-menu-btn {
        display: none;
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
    }
    
    .bg-munici-green {
        background: linear-gradient(135deg, var(--munici-green-dark), var(--munici-green));
    }
    
    .navbar-brand {
        padding
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .dashboard-header {
        background: linear-gradient(135deg, var(--munici-green), var(--munici-green-dark));
        color: white;
        padding: 1.5rem 0;
        margin-bottom: 1.5rem;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    /* Collapsible menu styling */
    .vertical-nav .nav-link[data-bs-toggle="collapse"] {
        position: relative;
    }
    
    .vertical-nav .toggle-icon {
        transition: transform 0.2s ease;
        font-size: 0.8rem;
        margin-left: auto;
    }
    
    .vertical-nav .nav-link[data-bs-toggle="collapse"][aria-expanded="true"] .toggle-icon {
        transform: rotate(180deg);
    }
    
    .vertical-nav .collapse {
        padding-left: 10px;
    }
    
    .vertical-nav .nav-item ul .nav-link {
        padding-left: 30px;
        font-size: 0.9rem;
    }

    /* Profile dropdown styling */
    .profile-dropdown {
        position: relative;
    }
    
    .profile-btn {
        background: none;
        border: none;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    
    .profile-btn:hover {
        background-color: rgba(255, 255, 255, 0.15);
    }
    
    .profile-btn .bi-person-circle {
        font-size: 1.5rem;
    }
    
    .profile-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        min-width: 200px;
        padding: 0.5rem 0;
        z-index: 1000;
        display: none;
    }
    
    .profile-dropdown-menu.show {
        display: block;
    }
    
    .profile-dropdown-item {
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #333;
        text-decoration: none;
        transition: background-color 0.2s;
    }
    
    .profile-dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .profile-dropdown-divider {
        margin: 0.5rem 0;
        border-top: 1px solid #dee2e6;
    }
    
    .profile-name {
        font-weight: 500;
        color: var(--munici-green-dark);
    }
    
    /* Change Password Modal */
    .password-toggle {
        cursor: pointer;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 5;
    }

    .btn-munici-green {
    background-color: var(--munici-green) !important;;
    color: white !important;;
    border: none;
}

.swal2-actions .btn {
  margin: 0 6px; /* space left and right */
}

.btn-munici-green:hover {
    background-color: #0E3B85;
    color: white;
}
    
    /* Mobile optimizations */
    @media (max-width: 992px) {
        .vertical-nav {
            position: fixed;
            z-index: 1000;
            height: 100vh;
            left: -220px;
            top: 56px;
        }
        
        .vertical-nav.show {
            left: 0;
        }
        
        .main-content {
            width: 100%;
            padding-top: 1rem;
        }
        
        .mobile-menu-btn {
            display: block !important;
        }
        
        .vertical-nav .nav-link {
            padding: 8px 12px;
        }
        
        .vertical-nav .nav-text {
            font-size: 0.9rem;
        }
        
        .vertical-nav .nav-item ul .nav-link {
            padding-left: 20px;
            font-size: 0.85rem;
        }
        
        .profile-name {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header h1 {
            font-size: 1.5rem;
        }
        
        .main-content {
            padding: 15px;
        }
    }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-munici-green">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="<?= $basePath ?>../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="40">
                MuniciHelp
            </a>
        </div>
    </nav>

    <main class="container-fluid p-0">