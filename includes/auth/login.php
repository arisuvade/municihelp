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

// Fetch walk-in programs from database
$walkinPrograms = [];
$programQuery = $conn->query("
    SELECT mt.id, mt.name, mt.parent_id, mt.is_online, 
           d.name as department_name, d.path as department_path
    FROM mswd_types mt
    JOIN departments d ON d.id = CASE 
        WHEN mt.parent_id IS NULL THEN 2  -- Default to MSWD for parent programs
        ELSE 2
    END
    WHERE mt.is_online = 0
    ORDER BY mt.parent_id IS NULL DESC, mt.parent_id, mt.name
");

if ($programQuery && $programQuery->num_rows > 0) {
    while ($program = $programQuery->fetch_assoc()) {
        // Get requirements for this program
        $reqQuery = $conn->prepare("
            SELECT name 
            FROM mswd_types_requirements 
            WHERE mswd_types_id = ?
        ");
        $reqQuery->bind_param("i", $program['id']);
        $reqQuery->execute();
        $reqResult = $reqQuery->get_result();
        
        $requirements = [];
        while ($req = $reqResult->fetch_assoc()) {
            $requirements[] = $req['name'];
        }
        $reqQuery->close();
        
        $program['requirements'] = $requirements;
        
        if ($program['parent_id'] === null) {
            // This is a parent program
            $walkinPrograms[$program['id']] = $program;
            $walkinPrograms[$program['id']]['sub_programs'] = [];
        } else {
            // This is a sub-program, add to parent
            if (isset($walkinPrograms[$program['parent_id']])) {
                $walkinPrograms[$program['parent_id']]['sub_programs'][] = $program;
            }
        }
    }
}

// Hardcoded PWD programs (all walk-in)
$pwdPrograms = [
    [
        'id' => 'pwd-1',
        'name' => 'Financial Assistance',
        'sub_programs' => [
            [
                'id' => 'pwd-101',
                'name' => 'Birthday Cash Give',
                'requirements' => ['Registered PWD ID']
            ],
            [
                'id' => 'pwd-102',
                'name' => 'In-School PWD',
                'requirements' => [
                    'PWD ID',
                    'Certificate of Enrollment',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 'pwd-103',
                'name' => 'SPED',
                'requirements' => [
                    'PWD ID',
                    'Certificate of Enrollment',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 'pwd-104',
                'name' => 'Alay ni MOM',
                'requirements' => [
                    'PWD ID',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 'pwd-105',
                'name' => 'Malasakit ni MOM',
                'requirements' => [
                    'PWD ID',
                    'Certificate of therapy',
                    'Parent ID'
                ]
            ],
            [
                'id' => 'pwd-106',
                'name' => 'Kalinga ni MOM',
                'requirements' => [
                    'PWD ID',
                    'Student ID',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 'pwd-107',
                'name' => 'Burial',
                'requirements' => ['Death certificate']
            ]
        ]
    ]
];

$pageTitle = 'Login';
$isAuthPage = true;
include '../../includes/header_simple.php';
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
    height: fit-content;
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

/* Mobile reordering */
@media (max-width: 767.98px) {
    .order-md-1 {
        order: 2;
    }
    
    .order-md-2 {
        order: 1;
    }
}
</style>

<div class="container-fluid">
    <div class="row min-vh-100">
        <!-- Right Side: Login Form (Moved to top on mobile) -->
        <div class="col-md-6 order-md-2 align-items-center justify-content-center p-4">
            <div class="auth-container">
                <div class="text-center mb-4">
                    <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
                    <h2 class="mt-3 text-munici-green">MuniciHelp Login</h2>
                    <p class="text-muted">Access your account to manage requests</p>
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
                    <a href="register.php" class="text-munici-green">Visit the registration page for instructions</a>
                </p>
            </div>
        </div>

        <!-- Left Side: Walk-in Programs Information (Moved to bottom on mobile) -->
        <div class="col-md-6 order-md-1 bg-light p-4 d-flex flex-column">
            <div class="walkin-header">
                <h2 class="text-munici-green">Walk-in Programs Information</h2>
                <p class="text-muted">Municipal Assistance Portal - Requirements for Walk-in Services</p>
            </div>

            <div class="walkin-info-container flex-grow-1">
                <div class="accordion" id="walkinAccordion">
                    <!-- MSWD Services -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingMSWD">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapseMSWD" aria-expanded="false" 
                                    aria-controls="collapseMSWD">
                                <i class="fas fa-hands-helping me-2"></i>
                                MSWD Services
                            </button>
                        </h2>
                        <div id="collapseMSWD" class="accordion-collapse collapse" 
                             aria-labelledby="headingMSWD" data-bs-parent="#walkinAccordion">
                            <div class="accordion-body">
                                <?php if (!empty($walkinPrograms)): ?>
                                    <div class="accordion sub-accordion" id="mswdSubAccordion">
                                        <?php 
                                        // Sort programs by ID to maintain order
                                        ksort($walkinPrograms);
                                        foreach ($walkinPrograms as $program): 
                                        ?>
                                            <div class="accordion-item">
                                                <h3 class="accordion-header" id="mswdHeading<?= $program['id'] ?>">
                                                    <button class="accordion-button collapsed" type="button" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#mswdCollapse<?= $program['id'] ?>" 
                                                            aria-expanded="false" 
                                                            aria-controls="mswdCollapse<?= $program['id'] ?>">
                                                        <i class="fas fa-chevron-right me-2"></i>
                                                        <?= htmlspecialchars($program['name']) ?>
                                                    </button>
                                                </h3>
                                                <div id="mswdCollapse<?= $program['id'] ?>" 
                                                     class="accordion-collapse collapse" 
                                                     aria-labelledby="mswdHeading<?= $program['id'] ?>" 
                                                     data-bs-parent="#mswdSubAccordion">
                                                    <div class="accordion-body">
                                                        <?php if (!empty($program['sub_programs'])): ?>
                                                            <div class="accordion sub-sub-accordion" id="mswdSubSubAccordion<?= $program['id'] ?>">
                                                                <?php 
                                                                // Sort sub-programs by ID to maintain order
                                                                usort($program['sub_programs'], function($a, $b) {
                                                                    return $a['id'] - $b['id'];
                                                                });
                                                                foreach ($program['sub_programs'] as $subProgram): 
                                                                ?>
                                                                    <div class="accordion-item">
                                                                        <h4 class="accordion-header" id="mswdSubHeading<?= $subProgram['id'] ?>">
                                                                            <button class="accordion-button collapsed" type="button" 
                                                                                    data-bs-toggle="collapse" 
                                                                                    data-bs-target="#mswdSubCollapse<?= $subProgram['id'] ?>" 
                                                                                    aria-expanded="false" 
                                                                                    aria-controls="mswdSubCollapse<?= $subProgram['id'] ?>">
                                                                                <i class="fas fa-chevron-right me-2"></i>
                                                                                <?= htmlspecialchars($subProgram['name']) ?>
                                                                            </button>
                                                                        </h4>
                                                                        <div id="mswdSubCollapse<?= $subProgram['id'] ?>" 
                                                                             class="accordion-collapse collapse" 
                                                                             aria-labelledby="mswdSubHeading<?= $subProgram['id'] ?>" 
                                                                             data-bs-parent="#mswdSubSubAccordion<?= $program['id'] ?>">
                                                                            <div class="accordion-body">
                                                                                <?php if (!empty($subProgram['requirements'])): ?>
                                                                                    <h6 class="text-munici-green mb-3">Requirements:</h6>
                                                                                    <ul class="list-group list-group-flush">
                                                                                        <?php foreach ($subProgram['requirements'] as $requirement): ?>
                                                                                            <li class="list-group-item">
                                                                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                                                                <?= htmlspecialchars($requirement) ?>
                                                                                            </li>
                                                                                        <?php endforeach; ?>
                                                                                    </ul>
                                                                                <?php else: ?>
                                                                                    <p class="text-muted">No specific requirements listed.</p>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <?php if (!empty($program['requirements'])): ?>
                                                                <h6 class="text-munici-green mb-3">Requirements:</h6>
                                                                <ul class="list-group list-group-flush">
                                                                    <?php foreach ($program['requirements'] as $requirement): ?>
                                                                        <li class="list-group-item">
                                                                            <i class="fas fa-check-circle text-success me-2"></i>
                                                                            <?= htmlspecialchars($requirement) ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p class="text-muted">No specific requirements listed.</p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No MSWD walk-in programs available at the moment.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- PWD Services -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingPWD">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapsePWD" aria-expanded="false" 
                                    aria-controls="collapsePWD">
                                <i class="fas fa-wheelchair me-2"></i>
                                PWD Services
                            </button>
                        </h2>
                        <div id="collapsePWD" class="accordion-collapse collapse" 
                             aria-labelledby="headingPWD" data-bs-parent="#walkinAccordion">
                            <div class="accordion-body">
                                <?php if (!empty($pwdPrograms)): ?>
                                    <div class="accordion sub-accordion" id="pwdSubAccordion">
                                        <?php foreach ($pwdPrograms as $program): ?>
                                            <div class="accordion-item">
                                                <h3 class="accordion-header" id="pwdHeading<?= $program['id'] ?>">
                                                    <button class="accordion-button collapsed" type="button" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#pwdCollapse<?= $program['id'] ?>" 
                                                            aria-expanded="false" 
                                                            aria-controls="pwdCollapse<?= $program['id'] ?>">
                                                        <i class="fas fa-chevron-right me-2"></i>
                                                        <?= htmlspecialchars($program['name']) ?>
                                                    </button>
                                                </h3>
                                                <div id="pwdCollapse<?= $program['id'] ?>" 
                                                     class="accordion-collapse collapse" 
                                                     aria-labelledby="pwdHeading<?= $program['id'] ?>" 
                                                     data-bs-parent="#pwdSubAccordion">
                                                    <div class="accordion-body">
                                                        <?php if (!empty($program['sub_programs'])): ?>
                                                            <div class="accordion sub-sub-accordion" id="pwdSubSubAccordion<?= $program['id'] ?>">
                                                                <?php foreach ($program['sub_programs'] as $subProgram): ?>
                                                                    <div class="accordion-item">
                                                                        <h4 class="accordion-header" id="pwdSubHeading<?= $subProgram['id'] ?>">
                                                                            <button class="accordion-button collapsed" type="button" 
                                                                                    data-bs-toggle="collapse" 
                                                                                    data-bs-target="#pwdSubCollapse<?= $subProgram['id'] ?>" 
                                                                                    aria-expanded="false" 
                                                                                    aria-controls="pwdSubCollapse<?= $subProgram['id'] ?>">
                                                                                <i class="fas fa-chevron-right me-2"></i>
                                                                                <?= htmlspecialchars($subProgram['name']) ?>
                                                                            </button>
                                                                        </h4>
                                                                        <div id="pwdSubCollapse<?= $subProgram['id'] ?>" 
                                                                             class="accordion-collapse collapse" 
                                                                             aria-labelledby="pwdSubHeading<?= $subProgram['id'] ?>" 
                                                                             data-bs-parent="#pwdSubSubAccordion<?= $program['id'] ?>">
                                                                            <div class="accordion-body">
                                                                                <?php if (!empty($subProgram['requirements'])): ?>
                                                                                    <h6 class="text-munici-green mb-3">Requirements:</h6>
                                                                                    <ul class="list-group list-group-flush">
                                                                                        <?php foreach ($subProgram['requirements'] as $requirement): ?>
                                                                                            <li class="list-group-item">
                                                                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                                                                <?= htmlspecialchars($requirement) ?>
                                                                                            </li>
                                                                                        <?php endforeach; ?>
                                                                                    </ul>
                                                                                <?php else: ?>
                                                                                    <p class="text-muted">No specific requirements listed.</p>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <?php if (!empty($program['requirements'])): ?>
                                                                <h6 class="text-munici-green mb-3">Requirements:</h6>
                                                                <ul class="list-group list-group-flush">
                                                                    <?php foreach ($program['requirements'] as $requirement): ?>
                                                                        <li class="list-group-item">
                                                                            <i class="fas fa-check-circle text-success me-2"></i>
                                                                            <?= htmlspecialchars($requirement) ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p class="text-muted">No specific requirements listed.</p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No PWD walk-in programs available at the moment.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQs Section -->
                <div class="mt-4">
                    <h4 class="text-munici-green mb-3">Frequently Asked Questions</h4>
                    
                    <div class="faq-item">
                        <h6>How do I register for an account?</h6>
                        <p>Click on the registration link below the login form or visit <a href="register.php" class="text-munici-green">registration page</a> for instructions on how to create your account.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h6>What are the office hours?</h6>
                        <p>Our offices are open Monday to Friday, 8:00 AM to 5:00 PM.</p>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="location-box">
                    <h5 class="text-munici-green mb-3">
                        <i class="fas fa-map-marker-alt me-2"></i>Office Locations
                    </h5>
                    <div class="mb-2">
                        <strong>MSWD Office:</strong> Municipal Hall, Pulilan, Bulacan
                    </div>
                    <div class="mb-2">
                        <strong>PWD Office:</strong> Former PUP, Pulilan, Bulacan
                    </div>
                    <p class="mb-0 text-muted">Please bring all required documents when visiting our offices.</p>
                </div>

                <!-- Advertisement for Online Services -->
                <div class="advertisement-box">
                    <h5 class="mb-3"><i class="fas fa-globe me-2"></i>Want to use our online services?</h5>
                    <p class="mb-3">Register for an account to access our convenient online services and submit requests from the comfort of your home!</p>
                    <a href="register.php" class="btn btn-light">
                        <i class="fas fa-user-plus me-2"></i>Register Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pw = document.getElementById('password');
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

<?php include '../../includes/footer.php'; ?>