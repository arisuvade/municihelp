<?php
// Detect the current page context
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Mayor's Office Paths
$isMayorPage = str_contains($currentPath, '/mayor/');
$isMayorSuperAdminPage = str_contains($currentPath, '/mayor/superadmin/');
$isMSWDPage = str_contains($currentPath, '/mayor/mswd/') && !str_contains($currentPath, '/mayor/mswd/admin/') && !str_contains($currentPath, '/mayor/mswd/mayor_admin/');
$isPWDPage = str_contains($currentPath, '/mayor/pwd/') && !str_contains($currentPath, '/mayor/pwd/admin/');
$isAnimalPage = str_contains($currentPath, '/mayor/animal/') && !str_contains($currentPath, '/mayor/animal/admin/') && !str_contains($currentPath, '/mayor/animal/pound_admin/');
$isMSWDAdminPage = str_contains($currentPath, '/mayor/mswd/admin/');
$isMayorAdminPage = str_contains($currentPath, '/mayor/mswd/mayor_admin/');
$isPWDAdminPage = str_contains($currentPath, '/mayor/pwd/admin/');
$isAnimalAdminPage = str_contains($currentPath, '/mayor/animal/admin/');
$isPoundAdminPage = str_contains($currentPath, '/mayor/animal/pound_admin/');
$isBarangayAdminPage = str_contains($currentPath, '/mayor/barangay/'); // Added for barangay admin

// Vice Mayor's Office Paths
$isViceMayorPage = str_contains($currentPath, '/vice_mayor/');
$isViceMayorSuperAdminPage = str_contains($currentPath, '/vice_mayor/superadmin/');
$isAssistancePage = str_contains($currentPath, '/vice_mayor/assistance/') && !str_contains($currentPath, '/vice_mayor/assistance/admin/');
$isAmbulancePage = str_contains($currentPath, '/vice_mayor/ambulance/') && !str_contains($currentPath, '/vice_mayor/ambulance/admin/');
$isAssistanceAdminPage = str_contains($currentPath, '/vice_mayor/assistance/admin/');
$isAmbulanceAdminPage = str_contains($currentPath, '/vice_mayor/ambulance/admin/');

// Other Paths
$isAuthPage = str_contains($currentPath, '/includes/auth/');
$isRootPage = !$isMayorPage && !$isViceMayorPage && !$isAuthPage;

// base path for user
if ($isAuthPage || $isMSWDAdminPage || $isMayorAdminPage || $isPWDAdminPage || $isAnimalAdminPage || $isPoundAdminPage || $isAssistanceAdminPage || $isAmbulanceAdminPage || $isBarangayAdminPage) {
    $basePath = '../../'; // All admin pages and auth pages
} elseif ($isMayorPage || $isViceMayorPage) {
    $basePath = '../'; // All mayor and vice mayor user pages
} else {
    $basePath = ''; // root level
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Determine dashboard link
if (isset($_SESSION['mayor_superadmin_id'])) {
    $dashboardLink = $basePath . '../mayor/superadmin/management.php';
} elseif (isset($_SESSION['vice_mayor_superadmin_id'])) {
    $dashboardLink = $basePath . '../vice_mayor/superadmin/management.php';
} elseif (isset($_SESSION['mswd_admin_id'])) {
    $dashboardLink = $basePath . '../mayor/mswd/admin/dashboard.php';
} elseif (isset($_SESSION['mayor_admin_id'])) {
    $dashboardLink = $basePath . '../mayor/mswd/mayor_admin/dashboard.php';
} elseif (isset($_SESSION['pwd_admin_id'])) {
    $dashboardLink = $basePath . '../mayor/pwd/admin/inquiries.php';
} elseif (isset($_SESSION['animal_admin_id'])) {
    $dashboardLink = $basePath . '../mayor/animal/admin/dashboard.php';
} elseif (isset($_SESSION['pound_admin_id'])) {
    $dashboardLink = $basePath . '../mayor/animal/pound_admin/dashboard.php';
} elseif (isset($_SESSION['assistance_admin_id'])) {
    $dashboardLink = $basePath . '../vice_mayor/assistance/admin/dashboard.php';
} elseif (isset($_SESSION['ambulance_admin_id'])) {
    $dashboardLink = $basePath . '../vice_mayor/ambulance/admin/dashboard.php';
} elseif (isset($_SESSION['barangay_admin_id'])) { // Added for barangay admin
    $dashboardLink = $basePath . '../mayor/barangay/index.php';
} elseif (isset($_SESSION['user_id'])) {
    $dashboardLink = $basePath . '../../../user/dashboard.php';
} else {
    $dashboardLink = $basePath . 'index.php';
}

// Get user/admin name for display
$userName = '';
if (isset($_SESSION['user_id'])) {
    // Get user name from users table
    include '../../../includes/db.php';
$stmt = $conn->prepare("SELECT name, middle_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $userName = trim(
        $user['name'] . ' ' .
        (!empty($user['middle_name']) ? $user['middle_name'] . ' ' : '') .
        $user['last_name']
    );
}
    $stmt->close();
} elseif (isset($_SESSION['admin_name'])) {
    $userName = $_SESSION['admin_name'];
} else {
    // Get admin name from admins table for various admin types
    $adminTypes = [
        'mayor_superadmin_id', 'vice_mayor_superadmin_id', 'mswd_admin_id', 
        'mayor_admin_id', 'pwd_admin_id', 'animal_admin_id', 'pound_admin_id', 
        'assistance_admin_id', 'ambulance_admin_id', 'barangay_admin_id'
    ];
    
    foreach ($adminTypes as $adminType) {
        if (isset($_SESSION[$adminType])) {
            include '../../../includes/db.php';
            $adminId = $_SESSION[$adminType];
            $stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                $userName = $admin['name'];
            }
            $stmt->close();
            break;
        }
    }
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
    <link href="<?= $basePath ?>assets/css/styles.css" rel="stylesheet">
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
            <?php if((isset($_SESSION['mayor_superadmin_id']) || isset($_SESSION['vice_mayor_superadmin_id']) || 
                isset($_SESSION['mswd_admin_id']) || isset($_SESSION['mayor_admin_id']) || 
                isset($_SESSION['pwd_admin_id']) || isset($_SESSION['animal_admin_id']) || 
                isset($_SESSION['pound_admin_id']) || isset($_SESSION['assistance_admin_id']) || 
                isset($_SESSION['ambulance_admin_id']) || isset($_SESSION['mayor_admin_id']) || isset($_SESSION['mswd_admin_id']) || isset($_SESSION['pwd_admin_id']) || isset($_SESSION['user_id'])) && !$isAuthPage): ?>
            <button class="mobile-menu-btn me-2" id="mobileMenuBtn">
                <i class="bi bi-list" id="mobileMenuIcon"></i>
            </button>
        <?php endif; ?>
            <a class="navbar-brand" href="<?= $dashboardLink ?>">
                <img src="<?= $basePath ?>../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="40">
                MuniciHelp
            </a>
            <?php if(!$isAuthPage): ?>
                <div class="d-flex align-items-center">
                    <?php if(isset($_SESSION['mayor_superadmin_id']) || isset($_SESSION['vice_mayor_superadmin_id']) || 
                        isset($_SESSION['mswd_admin_id']) || isset($_SESSION['mayor_admin_id']) || 
                        isset($_SESSION['pwd_admin_id']) || isset($_SESSION['animal_admin_id']) || 
                        isset($_SESSION['pound_admin_id']) || isset($_SESSION['assistance_admin_id']) || 
                        isset($_SESSION['ambulance_admin_id']) || isset($_SESSION['barangay_admin_id']) || isset($_SESSION['mayor_admin_id']) || isset($_SESSION['mswd_admin_id']) || isset($_SESSION['pwd_admin_id']) || 
                        isset($_SESSION['user_id'])): ?>
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileDropdownBtn">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <div class="profile-dropdown-menu" id="profileDropdownMenu">
                            <div class="profile-dropdown-item">
                                <i class="bi bi-person"></i>
                                <span><?= htmlspecialchars($userName) ?></span>
                            </div>
                            <div class="profile-dropdown-divider"></div>
                            <a href="#" class="profile-dropdown-item" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                <i class="bi bi-key"></i>
                                <span>Change Password</span>
                            </a>
                            <div class="profile-dropdown-divider"></div>
                            <a href="#" class="profile-dropdown-item text-danger" id="logoutButton">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                <span class="password-toggle" onclick="togglePassword('currentPassword')">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="newPassword" name="newPassword" required minlength="8">
                                <span class="password-toggle" onclick="togglePassword('newPassword')">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required minlength="8">
                                <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="form-text">Re-enter your new password to confirm.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-munici-green" id="savePasswordBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

<?php if((isset($_SESSION['mayor_superadmin_id']) || isset($_SESSION['vice_mayor_superadmin_id']) || 
         isset($_SESSION['mswd_admin_id']) || isset($_SESSION['mayor_admin_id']) || 
         isset($_SESSION['pwd_admin_id']) || isset($_SESSION['animal_admin_id']) || 
         isset($_SESSION['pound_admin_id']) || isset($_SESSION['assistance_admin_id']) || isset($_SESSION['mayor_admin_id']) || isset($_SESSION['mswd_admin_id']) || isset($_SESSION['pwd_admin_id']) ||
         isset($_SESSION['ambulance_admin_id']) || isset($_SESSION['barangay_admin_id']) || (isset($_SESSION['user_id']) && !$isAuthPage))): ?>
             <div class="main-container">
        <nav class="vertical-nav" id="sidebar">
            <ul class="nav flex-column px-2">
                <?php if(isset($_SESSION['vice_mayor_superadmin_id'])): ?>
                    <!-- Vice Mayor Superadmin -->
                    <li class="nav-item">
                        <div class="text-white px-3 small fw-bold text-uppercase">Management</div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'management.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>superadmin/management.php">
                            <i class="bi-person-gear"></i>
                            <span class="nav-text ms-2">Admins</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'users.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/users.php">
                            <i class="bi bi-people"></i>
                            <span class="nav-text ms-2">Users</span>
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <div class="text-white px-3 small fw-bold text-uppercase">Reports</div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'assistance_reports.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/assistance_reports.php">
                            <i class="bi bi-people-fill"></i>
                            <span class="nav-text ms-2">Assistance</span>
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'ambulance_reports.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/ambulance_reports.php">
                            <i class="bi bi-heart-pulse-fill"></i>
                            <span class="nav-text ms-2">Ambulance</span>
                        </a>
                    </li> -->

                <?php elseif(isset($_SESSION['mayor_superadmin_id'])): ?>
                    <!-- Mayor Superadmin -->
                    <li class="nav-item">
                        <div class="text-white px-3 small fw-bold text-uppercase">Management</div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'management.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/management.php">
                            <i class="bi bi-person-gear"></i>
                            <span class="nav-text ms-2">Admins</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'users.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/users.php">
                            <i class="bi bi-people"></i>
                            <span class="nav-text ms-2">Users</span>
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <div class="text-white px-3 small fw-bold text-uppercase">Reports</div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'mswd_reports.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/mswd_reports.php">
                            <i class="bi bi-person-arms-up"></i>
                            <span class="nav-text ms-2">MSWD</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'animal_reports.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>superadmin/animal_reports.php">
                            <i class="bi bi-house-heart-fill"></i>
                            <span class="nav-text ms-2">Animal</span>
                        </a>
                    </li>

                <?php elseif(isset($_SESSION['mayor_admin_id'])): ?>
                    <!-- Mayor Admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/mayor_admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'walkin.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>mswd/mayor_admin/walkin.php">
                            <i class="bi bi-person-walking"></i>
                            <span class="nav-text ms-2">Walk-in</span>
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/mayor_admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'pending.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/mayor_admin/pending.php">
                            <i class="bi bi-hourglass-split"></i>
                            <span class="nav-text ms-2">Pending</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/mayor_admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>
                
                <?php elseif(isset($_SESSION['mswd_admin_id'])): ?>
                    <!-- MSWD Admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'inquiries.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>mswd/admin/inquiries.php">
                            <i class="bi bi-question-circle"></i>
                            <span class="nav-text ms-2">Inquiries</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'mayor_approved.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/mayor_approved.php">
                            <i class="bi bi-check-square"></i>
                            <span class="nav-text ms-2">Mayor Approved</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'mswd_approved.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/mswd_approved.php">
                            <i class="bi bi-check-circle"></i>
                            <span class="nav-text ms-2">MSWD Approved</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'beneficiaries.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/beneficiaries.php">
                            <i class="bi bi-people"></i>
                            <span class="nav-text ms-2">Beneficiaries</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'inventory.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/inventory.php">
                            <i class="bi bi-wrench"></i>
                            <span class="nav-text ms-2">Inventory</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>mswd/admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>

                <?php elseif(isset($_SESSION['pwd_admin_id'])): ?>
                    <!-- PWD Admin -->
                     <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'inquiries.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>pwd/admin/inquiries.php">
                            <i class="bi bi-question-circle"></i>
                            <span class="nav-text ms-2">Inquiries</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'members.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>pwd/admin/members.php">
                            <i class="bi bi-people"></i>
                            <span class="nav-text ms-2">Members</span>
                        </a>
                    </li>

                                <?php elseif(isset($_SESSION['animal_admin_id'])): ?>
                    <!-- Animal Admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'inquiries.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>animal/admin/inquiries.php">
                            <i class="bi bi-question-circle"></i>
                            <span class="nav-text ms-2">Inquiries</span>
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'walkin.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>animal/admin/walkin.php">
                            <i class="bi bi-person-walking"></i>
                            <span class="nav-text ms-2">Walk-in</span>
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'management.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/admin/management.php">
                            <i class="bi bi-kanban"></i>
                            <span class="nav-text ms-2">Dog Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'pending.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/admin/pending.php">
                            <i class="bi bi-hourglass-split"></i>
                            <span class="nav-text ms-2">Pending</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'claimers.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/admin/claimers.php">
                            <i class="bi bi-people-fill"></i>
                            <span class="nav-text ms-2">Claimers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>
                
                <?php elseif(isset($_SESSION['pound_admin_id'])): ?>
                    <!-- Pound Admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/pound_admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'management.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/pound_admin/management.php">
                            <i class="bi bi-kanban"></i>
                            <span class="nav-text ms-2">Dog Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/pound_admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'approved.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/pound_admin/approved.php">
                            <i class="bi bi-check-circle"></i>
                            <span class="nav-text ms-2">Approved</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>animal/pound_admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>

                <?php elseif(isset($_SESSION['assistance_admin_id'])): ?>
                    <!-- Assistance Admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>assistance/admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'inquiries.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>assistance/admin/inquiries.php">
                            <i class="bi bi-question-circle"></i>
                            <span class="nav-text ms-2">Inquiries</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'walkin.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>assistance/admin/walkin.php">
                            <i class="bi bi-person-walking"></i>
                            <span class="nav-text ms-2">Walk-in</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>assistance/admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'pending.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>assistance/admin/pending.php">
                            <i class="bi bi-hourglass-split"></i>
                            <span class="nav-text ms-2">Pending</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'approved.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>assistance/admin/approved.php">
                            <i class="bi bi-check-circle"></i>
                            <span class="nav-text ms-2">Approved</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>assistance/admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>
                <?php elseif(isset($_SESSION['ambulance_admin_id'])): ?>
                    <!-- Ambulance Admin -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>ambulance/admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'inquiries.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>ambulance/admin/inquiries.php">
                            <i class="bi bi-question-circle"></i>
                            <span class="nav-text ms-2">Inquiries</span>
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'walkin.php' ? 'active' : '' ?>" 
                        href="<?= $basePath ?>ambulance/admin/walkin.php">
                            <i class="bi bi-person-walking"></i>
                            <span class="nav-text ms-2">Walk-in</span>
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'requests.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>ambulance/admin/requests.php">
                            <i class="bi bi-list-check"></i>
                            <span class="nav-text ms-2">All Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'pending.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>ambulance/admin/pending.php">
                            <i class="bi bi-hourglass-split"></i>
                            <span class="nav-text ms-2">Pending</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'scheduled.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>ambulance/admin/scheduled.php">
                            <i class="bi bi-calendar-check"></i>
                            <span class="nav-text ms-2">Scheduled</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
                           href="<?= $basePath ?>ambulance/admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span class="nav-text ms-2">Reports</span>
                        </a>
                    </li>
                <?php elseif(isset($_SESSION['user_id'])): ?>
                    <!-- User -->
                    <li class="nav-item">
                        <a class="nav-link <?= ($isRootPage && $currentPage == 'dashboard.php') ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../user/dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span class="nav-text ms-2">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($isRootPage && $currentPage == 'inquiry.php') ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../user/inquiry.php">
                            <i class="bi bi-question-circle"></i>
                            <span class="nav-text ms-2">Inquiry</span>
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <div class="text-white px-3 small fw-bold text-uppercase">Mayor RJ Peralta</div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $isMSWDPage ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../mayor/mswd/index.php"
                        onclick="handleMenuClick(event, 'mswdCollapse')">
                            <i class="bi bi-hospital"></i>
                            <span class="nav-text ms-2">MSWD</span>
                        </a>
                        <div class="collapse <?= $isMSWDPage ? 'show' : '' ?>" id="mswdCollapse">
                            <ul class="nav flex-column ms-4">
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isMSWDPage && $currentPage == 'index.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../mayor/mswd/index.php">
                                        <i class="bi bi-file-earmark-plus"></i>
                                        <span class="nav-text ms-2">Form</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isMSWDPage && $currentPage == 'status.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../mayor/mswd/status.php">
                                        <i class="bi bi-list-check"></i>
                                        <span class="nav-text ms-2">Status</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $isPWDPage ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../mayor/pwd/index.php">
                            <i class="bi bi-universal-access"></i>
                            <span class="nav-text ms-2">PWD</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $isAnimalPage ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../mayor/animal/claiming.php"
                        onclick="handleMenuClick(event, 'animalCollapse')">
                            <i class="bi bi-shield-plus"></i>
                            <span class="nav-text ms-2">Animal Control</span>
                        </a>
                        <div class="collapse <?= $isAnimalPage ? 'show' : '' ?>" id="animalCollapse">
                            <ul class="nav flex-column ms-4">
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAnimalPage && $currentPage == 'claiming.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../mayor/animal/claiming.php">
                                        <i class="bi bi-box-arrow-in-down"></i>
                                        <span class="nav-text ms-2">Claiming</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAnimalPage && $currentPage == 'adoption.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../mayor/animal/adoption.php">
                                        <i class="bi bi-heart"></i>
                                        <span class="nav-text ms-2">Adoption</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAnimalPage && $currentPage == 'rabid.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../mayor/animal/rabid.php">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <span class="nav-text ms-2">Rabid Report</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAnimalPage && $currentPage == 'status.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../mayor/animal/status.php">
                                        <i class="bi bi-list-check"></i>
                                        <span class="nav-text ms-2">Status</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item mt-3">
                        <div class="text-white px-3 small fw-bold text-uppercase">VM Atty. Imee Cruz</div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $isAssistancePage ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../vice_mayor/assistance/index.php"
                        onclick="handleMenuClick(event, 'assistanceCollapse')">
                            <i class="bi bi-wallet2"></i>
                            <span class="nav-text ms-2">Assistance</span>
                        </a>
                        <div class="collapse <?= $isAssistancePage ? 'show' : '' ?>" id="assistanceCollapse">
                            <ul class="nav flex-column ms-4">
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAssistancePage && $currentPage == 'index.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../vice_mayor/assistance/index.php">
                                        <i class="bi bi-file-earmark-plus"></i>
                                        <span class="nav-text ms-2">Form</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAssistancePage && $currentPage == 'status.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>../../../vice_mayor/assistance/status.php">
                                        <i class="bi bi-list-check"></i>
                                        <span class="nav-text ms-2">Status</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link <?= $isAmbulancePage ? 'active' : '' ?>" 
                        href="<?= $basePath ?>../../../vice_mayor/ambulance/index.php"
                        onclick="handleMenuClick(event, 'ambulanceCollapse')">
                            <i class="bi bi-truck-front"></i>
                            <span class="nav-text ms-2">Ambulance</span>
                        </a>
                        <div class="collapse <?= $isAmbulancePage ? 'show' : '' ?>" id="ambulanceCollapse">
                            <ul class="nav flex-column ms-4">
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAmbulancePage && $currentPage == 'index.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>ambulance/index.php">
                                        <i class="bi bi-file-earmark-plus"></i>
                                        <span class="nav-text ms-2">Form</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($isAmbulancePage && $currentPage == 'status.php') ? 'active' : '' ?>" 
                                    href="<?= $basePath ?>ambulance/status.php">
                                        <i class="bi bi-list-check"></i>
                                        <span class="nav-text ms-2">Status</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li> -->
                <?php endif; ?>
            </ul>
        </nav>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <main class="main-content" id="mainContent">
<?php else: ?>
    <main class="container my-5" style="padding-top: -80px">
<?php endif; ?>

<!-- Success Password Change Modal -->
<div class="modal fade" id="successPasswordModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Password Changed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h4 class="mt-3">Password Updated Successfully!</h4>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Continue</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Password Change Modal -->
<div class="modal fade" id="errorPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 3rem;"></i>
                <h4 class="mt-3" id="errorPasswordMessage">An error occurred</h4>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>    

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenuIcon = document.getElementById('mobileMenuIcon');
    const logoutButton = document.getElementById('logoutButton');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const profileDropdownBtn = document.getElementById('profileDropdownBtn');
    const profileDropdownMenu = document.getElementById('profileDropdownMenu');
    const savePasswordBtn = document.getElementById('savePasswordBtn');
    
    // Mobile menu toggle
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            if (sidebar.classList.contains('show')) {
                mobileMenuIcon.classList.remove('bi-list');
                mobileMenuIcon.classList.add('bi-x');
            } else {
                mobileMenuIcon.classList.remove('bi-x');
                mobileMenuIcon.classList.add('bi-list');
            }
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            mobileMenuIcon.classList.remove('bi-x');
            mobileMenuIcon.classList.add('bi-list');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnMobileMenuBtn = mobileMenuBtn && mobileMenuBtn.contains(event.target);
            
            if (!isClickInsideSidebar && !isClickOnMobileMenuBtn && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                if (mobileMenuIcon) {
                    mobileMenuIcon.classList.remove('bi-x');
                    mobileMenuIcon.classList.add('bi-list');
                }
            }
        }
    });
    
    // Profile dropdown toggle
    if (profileDropdownBtn && profileDropdownMenu) {
        profileDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdownMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileDropdownBtn.contains(e.target) && !profileDropdownMenu.contains(e.target)) {
                profileDropdownMenu.classList.remove('show');
            }
        });
    }
    
    // Logout confirmation
    // Replace the current logout functionality with this code
if (logoutButton) {
    logoutButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out of the system",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel',
            background: '#fff',
            iconColor: '#dc3545',
            customClass: {
                confirmButton: 'btn btn-munici-green',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "<?= $basePath ?>../../../../includes/auth/logout.php";
            }
        });
    });
}
    
// Save password functionality
if (savePasswordBtn) {
    savePasswordBtn.addEventListener('click', function() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        // Validate form - check current password first
        if (!currentPassword) {
            document.getElementById('errorPasswordMessage').textContent = 'Current password is required';
            const errorModal = new bootstrap.Modal(document.getElementById('errorPasswordModal'));
            errorModal.show();
            return;
        }
        
        // Then check new password
        if (!newPassword) {
            document.getElementById('errorPasswordMessage').textContent = 'New password is required';
            const errorModal = new bootstrap.Modal(document.getElementById('errorPasswordModal'));
            errorModal.show();
            return;
        }
        
        if (newPassword.length < 8) {
            document.getElementById('errorPasswordMessage').textContent = 'New password must be at least 8 characters long';
            const errorModal = new bootstrap.Modal(document.getElementById('errorPasswordModal'));
            errorModal.show();
            return;
        }
        
        // Then check confirm password
        if (!confirmPassword) {
            document.getElementById('errorPasswordMessage').textContent = 'Please confirm your new password';
            const errorModal = new bootstrap.Modal(document.getElementById('errorPasswordModal'));
            errorModal.show();
            return;
        }
        
        if (newPassword !== confirmPassword) {
            document.getElementById('errorPasswordMessage').textContent = 'New passwords do not match';
            const errorModal = new bootstrap.Modal(document.getElementById('errorPasswordModal'));
            errorModal.show();
            return;
        }
        
        // Submit via AJAX
$.ajax({
    url: '<?= $basePath ?>../includes/change_password.php',
    type: 'POST',
    data: {
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmPassword: confirmPassword
    },
    success: function(response) {
    // Remove the JSON.parse() since jQuery already parsed it
    // response is already a JavaScript object
    if (response.success) {
        // Hide the change password modal first
        $('#changePasswordModal').modal('hide');
        
        // Reset the form
        document.getElementById('changePasswordForm').reset();
        
        // Show success modal after a short delay to allow the first modal to close
        setTimeout(function() {
            const successModal = new bootstrap.Modal(document.getElementById('successPasswordModal'));
            successModal.show();
            
            // Add event listener to properly close the success modal
            document.getElementById('successPasswordModal').addEventListener('hidden.bs.modal', function () {
                // Remove the modal backdrop if it's still there
                $('.modal-backdrop').remove();
                // Ensure body doesn't have modal-open class
                $('body').removeClass('modal-open');
            });
        }, 300);
    } else {
        // Show error modal with specific message
        document.getElementById('errorPasswordMessage').textContent = response.message;
        const errorModal = new bootstrap.Modal(document.getElementById('errorPasswordModal'));
        errorModal.show();
        
        // Add event listener to properly close the error modal
        document.getElementById('errorPasswordModal').addEventListener('hidden.bs.modal', function () {
            // Remove the modal backdrop if it's still there
            $('.modal-backdrop').remove();
            // Ensure body doesn't have modal-open class
            $('body').removeClass('modal-open');
        });
    }
}
});
    });
}
    
    // Collapsible menu behavior - initialize all collapse elements
    const collapseElements = [
        'mswdCollapse',
        'pwdCollapse',
        'animalCollapse',
        'assistanceCollapse',
        'ambulanceCollapse'
    ];
    
    function setupCollapse(collapseId) {
        const collapseElement = document.getElementById(collapseId);
        if (collapseElement) {
            collapseElement.addEventListener('show.bs.collapse', function() {
                const icon = this.previousElementSibling.querySelector('.toggle-icon');
                if (icon) {
                    icon.classList.add('bi-chevron-up');
                    icon.classList.remove('bi-chevron-down');
                }
            });
            
            collapseElement.addEventListener('hide.bs.collapse', function() {
                const icon = this.previousElementSibling.querySelector('.toggle-icon');
                if (icon) {
                    icon.classList.add('bi-chevron-down');
                    icon.classList.remove('bi-chevron-up');
                }
            });
        }
    }
    
    // Setup all collapse elements
    collapseElements.forEach(setupCollapse);
});

function handleMenuClick(event, collapseId) {
    // Prevent default if clicking the chevron icon
    if (event.target.classList.contains('toggle-icon')) {
        event.preventDefault();
        const collapseElement = document.getElementById(collapseId);
        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement);
        bsCollapse.toggle();
        
        // Close all other dropdowns
        const allCollapseIds = ['mswdCollapse', 'pwdCollapse', 'animalCollapse', 'assistanceCollapse', 'ambulanceCollapse'];
        allCollapseIds.forEach(id => {
            if (id !== collapseId) {
                const otherCollapse = document.getElementById(id);
                if (otherCollapse) {
                    bootstrap.Collapse.getInstance(otherCollapse)?.hide();
                }
            }
        });
    } else {
        // For the main link, let it navigate to the form page
        // But first ensure the dropdown is shown
        const collapseElement = document.getElementById(collapseId);
        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement);
        bsCollapse.show();
        
        // Close all other dropdowns
        const allCollapseIds = ['mswdCollapse', 'pwdCollapse', 'animalCollapse', 'assistanceCollapse', 'ambulanceCollapse'];
        allCollapseIds.forEach(id => {
            if (id !== collapseId) {
                const otherCollapse = document.getElementById(id);
                if (otherCollapse) {
                    bootstrap.Collapse.getInstance(otherCollapse)?.hide();
                }
            }
        });
    }
}

// Toggle password visibility
function togglePassword(inputId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
}
</script>