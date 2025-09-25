<?php
session_start();
include '../../includes/db.php';

// Check if barangay admin is logged in
if (!isset($_SESSION['barangay_admin_id'])) {
    header("Location: ../../includes/auth/login.php");
    exit();
}

// Get barangay admin info
$barangay_admin_id = $_SESSION['barangay_admin_id'];
$admin_query = $conn->prepare("SELECT a.name, a.department_id, d.name as department_name 
                              FROM admins a 
                              LEFT JOIN departments d ON a.department_id = d.id 
                              WHERE a.id = ?");
$admin_query->bind_param("i", $barangay_admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();
$admin_data = $admin_result->fetch_assoc();

if (!$admin_data) {
    die("Admin not found");
}

$admin_name = $admin_data['name'] ?? 'Barangay Admin';
$department_id = $admin_data['department_id'];
$department_name = $admin_data['department_name'];

// Since departments represent barangays, we need to find the corresponding barangay_id
// Let's assume the department name matches the barangay name
$barangay_query = $conn->prepare("SELECT id, name FROM barangays WHERE name = ?");
$barangay_query->bind_param("s", $department_name);
$barangay_query->execute();
$barangay_result = $barangay_query->get_result();
$barangay_data = $barangay_result->fetch_assoc();

if (!$barangay_data) {
    // If no direct match, try to find a barangay with a similar name
    // This is a fallback approach
    $similar_query = $conn->prepare("SELECT id, name FROM barangays WHERE name LIKE ?");
    $similar_name = "%" . $department_name . "%";
    $similar_query->bind_param("s", $similar_name);
    $similar_query->execute();
    $similar_result = $similar_query->get_result();
    $barangay_data = $similar_result->fetch_assoc();
    
    if (!$barangay_data) {
        die("Barangay not found for department: " . htmlspecialchars($department_name));
    }
}

$barangay_id = $barangay_data['id'];
$barangay_name = $barangay_data['name'];

function formatPhoneNumber($phone) {
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Get all barangays for filter (only departments with parent_id=1 AND excluding IDs 2-6)
$barangays_query = $conn->query("SELECT id, name FROM departments WHERE parent_id = 1 AND id NOT IN (1,2,3,4,5,6) ORDER BY name");
$barangays = [];
while ($row = $barangays_query->fetch_assoc()) {
    $barangays[$row['id']] = $row['name'];
}

// Filter handling - MODIFIED: Only show users from the current barangay
$where = ["u.barangay_id = ?"];
$params = [$barangay_id];
$types = 'i';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(CONCAT(u.name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name) LIKE ? OR u.id = ? OR u.phone LIKE ?)";
    $params[] = $search;
    $params[] = $_GET['search'];
    $params[] = '%' . $_GET['search'] . '%';
    $types .= 'sss';
}

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $where[] = "DATE(u.created_at) = ?";
    $params[] = $_GET['date'];
    $types .= 's';
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get admin account
$admin_account_query = $conn->prepare("
    SELECT a.id, a.name, a.phone, a.department_id, d.name as barangay_name, a.created_at 
    FROM admins a 
    JOIN departments d ON a.department_id = d.id
    WHERE a.id = ?
");
$admin_account_query->bind_param("i", $barangay_admin_id);
$admin_account_query->execute();
$admin_account = $admin_account_query->get_result()->fetch_assoc();

// Get user accounts with filters - MODIFIED: Only users from current barangay
$users_query = $conn->prepare("
    SELECT 
        u.id, 
        u.name,
        u.middle_name,
        u.last_name,
        u.birthday, 
        u.phone, 
        u.address,  -- ADD THIS LINE
        u.barangay_id, 
        b.name as barangay_name, 
        a.name as created_by, 
        u.created_at 
    FROM users u 
    LEFT JOIN barangays b ON u.barangay_id = b.id
    LEFT JOIN admins a ON u.createdby_admin_id = a.id
    $whereClause
    ORDER BY u.created_at DESC
");

if (!empty($params)) {
    $users_query->bind_param($types, ...$params);
}

$users_query->execute();
$users = $users_query->get_result();

$pageTitle = 'User Management';
include '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="../../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --approved: #28A745;
            --scheduled: #4361ee;
            --completed: #6a5acd;
            --declined: #DC3545;
            --cancelled: #6C757D;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .vertical-nav {
        display: none !important;
    }
    
    .main-container {
        display: block !important;
    }
    
    .main-content {
        width: 100% !important;
        margin-left: 0 !important;
    }
        
        .dashboard-container {
            width: 95%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--munici-green), #0E3B85);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
  .admin-badge {
        position: absolute;
        right: 20px;
        top: 20px;
        background: #28a745;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        color: white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .admin-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
    }
    
    .admin-badge i {
        margin-right: 8px;
        filter: brightness(0) invert(1); /* Makes the icon white */
    }
        
        .filter-card {
            margin-bottom: 1.5rem;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .recent-activity-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .recent-activity-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.2rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        /* Column widths */
        .table-col-name {
            width: 26%;
        }

        .table-col-phone {
            width: 18%;
            text-align: center;
        }
        
        .table-col-admin {
            width: 19%;
            text-align: center;
        }
        
        .table-col-date {
            width: 20%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 17%;
            text-align: center;
        }

        .table-col-admin-name {
            width: 26%;
        }
        
        .table-col-admin-phone {
            width: 15%;
            text-align: center;
        }
        
        .table-col-admin-barangay {
            width: 15%;
            text-align: center;
        }
        
        .table-col-admin-date {
            width: 30%;
            text-align: center;
        }
        
        .table-col-admin-actions {
            width: 14%;
            text-align: center;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-top: none;
            padding: 0.75rem 1rem;
            background-color: #f8f9fa;
        }
        
        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.03);
        }

        /* Button styles matching the reference */
        .btn-view {
            background-color: var(--scheduled);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-view:hover {
            background-color: #0E3B85;
            color: white;
        }
        
        .btn-edit {
            background-color: var(--approved);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-edit:hover {
            background-color: #218838;
            color: white;
        }
        
        .btn-delete {
            background-color: var(--declined);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }
        
        .btn-add {
            background-color: var(--munici-green);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-add:hover {
            background-color: #0E3B85;
            color: white;
        }
        
        .no-users {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-users i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        /* Password toggle eye */
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
        }
        
        .password-input-group {
            position: relative;
        }
        
        /* Button styles */
        .btn-munici-green {
            background-color: var(--munici-green);
            color: white;
        }
        
        .btn-munici-green:hover {
            background-color: #0E3B85;
            color: white;
        }
        
        .btn-save {
            background-color: var(--munici-green);
            color: white;
        }
        
        .btn-save:hover {
            background-color: #0E3B85;
            color: white;
        }
        
        /* Mobile specific styles */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            
            .mobile-row {
                display: flex;
                flex-direction: column;
                padding: 1rem;
                border-bottom: 1px solid #dee2e6;
            }
            
            .mobile-row div {
                margin-bottom: 0.5rem;
            }
            
            .mobile-label {
                font-weight: 600;
                color: #495057;
                margin-right: 0.5rem;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-table {
                display: block;
            }
            
            /* Adjust column widths for mobile */
            .table-col-actions {
                min-width: unset;
            }
            
            .admin-badge {
                position: static;
                margin-top: 10px;
                justify-content: flex-end;
            }
            
            /* Button adjustments for mobile */
            .btn-edit, .btn-delete {
                padding: 0.25rem 0.5rem;
                font-size: 0.85rem;
                width: 100%;
            }
            
            .btn-edit i, .btn-delete i {
                margin-right: 0 !important;
            }
            
            .action-text {
                display: none;
            }
        }
        
        @media (min-width: 769px) {
            .desktop-table {
                display: table;
                width: 100%;
            }
            
            .mobile-table {
                display: none;
            }
            
            .action-text {
                display: inline;
            }
            
            .btn-edit i, .btn-delete i {
                margin-right: 5px;
            }
        }

        /* Success modal styles */
        @keyframes iconAppear {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .fa-check-circle {
            animation: iconAppear 0.5s ease-in-out;
        }

        .disabled-dropdown {
            pointer-events: none;
            background-color: #e9ecef;
            opacity: 1;
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.btn-add {
    background-color: var(--munici-green);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    border-radius: 6px;
    transition: all 0.3s;
}

.btn-add:hover {
    background-color: #0E3B85;
    color: white;
}

@media (max-width: 768px) {
    .btn-add {
        padding: 8px 15px;
        font-size: 0.8rem;
    }
}
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Barangay <?= htmlspecialchars($barangay_name) ?></h1>
                    <p class="mb-0">Manage user accounts in your barangay</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Action Header -->
    <div class="action-header">
        <h2 class="mb-0">User Accounts</h2>
        <button class="btn btn-add" id="addUserBtn">
            <i class="fas fa-plus"></i> Add User
        </button>
    </div>

    <!-- Filter Section -->
    <div class="card filter-card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-9">
                <label class="form-label">Search Users</label>
                <input type="text" class="form-control" id="searchUsersFilter" 
                    placeholder="Search by name, ID or phone" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date Created</label>
                <input type="date" class="form-control" id="dateUsersFilter" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>

        <!-- Admin Account Section -->
<div class="recent-activity-card card mb-4">
        <!-- User Accounts Section -->
        <div class="recent-activity-card card">
            <div class="card-header">
                <h4 class="mb-0">Resident Accounts</h4>
            </div>
            <div class="card-body p-0">
                <?php if ($users->num_rows == 0 && empty($_GET)): ?>
                    <div class="no-users">
                        <i class="fas fa-users"></i>
                        <h4>No Resident Accounts</h4>
                        <p>There are no resident accounts in your barangay.</p>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table -->
                    <div class="table-responsive desktop-table">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th class="table-col-name">Name</th>
                                    <th class="table-col-phone">Phone</th>
                                    <th class="table-col-admin">Created By</th>
                                    <th class="table-col-date">Date Created</th>
                                    <th class="table-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php while ($user = $users->fetch_assoc()): 
    $phone_number = formatPhoneNumber($user['phone']);
    $full_name = $user['name'] . 
        ($user['middle_name'] ? ' ' . $user['middle_name'] : '') . 
        ' ' . $user['last_name'];
?>
    <tr class="user-row" 
        data-id="<?= $user['id'] ?>"
        data-first-name="<?= htmlspecialchars($user['name']) ?>"
        data-middle-name="<?= htmlspecialchars($user['middle_name'] ?? '') ?>"
        data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
        data-birthday="<?= htmlspecialchars($user['birthday'] ?? '') ?>"
        data-address="<?= htmlspecialchars($user['address'] ?? '') ?>"
        data-phone="<?= htmlspecialchars($phone_number) ?>"
        data-barangay="<?= htmlspecialchars($user['barangay_id'] ?? '') ?>"
        data-date="<?= date('Y-m-d', strtotime($user['created_at'])) ?>">
    <td class="table-col-name"><?= htmlspecialchars($full_name) ?></td>
    <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
    <td class="table-col-admin"><?= htmlspecialchars($user['created_by'] ?? 'System') ?></td>
    <td class="table-col-date"><?= date('F j, Y', strtotime($user['created_at'])) ?></td>
    <td class="table-col-actions">
        <div class="action-buttons">
            <button class="btn btn-view view-btn" data-id="<?= $user['id'] ?>">
                <i class="fas fa-eye"></i> <span class="action-text">View</span>
            </button>
            <button class="btn btn-edit edit-btn" data-id="<?= $user['id'] ?>">
                <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
            </button>
            <button class="btn btn-delete delete-btn" data-id="<?= $user['id'] ?>">
                <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
            </button>
        </div>
    </td>
</tr>
<?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Table -->
                    <div class="mobile-table" id="mobileUsersTable">
                        <?php 
$users->data_seek(0);
while ($user = $users->fetch_assoc()): 
    $phone_number = formatPhoneNumber($user['phone']);
    $full_name = $user['name'] . 
        ($user['middle_name'] ? ' ' . $user['middle_name'] : '') . 
        ' ' . $user['last_name'];
?>
    <div class="mobile-row user-row" 
        data-id="<?= $user['id'] ?>"
        data-first-name="<?= htmlspecialchars($user['name']) ?>"
        data-middle-name="<?= htmlspecialchars($user['middle_name'] ?? '') ?>"
        data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
        data-birthday="<?= htmlspecialchars($user['birthday'] ?? '') ?>"
        data-address="<?= htmlspecialchars($user['address'] ?? '') ?>"
        data-phone="<?= htmlspecialchars($phone_number) ?>"
        data-barangay="<?= htmlspecialchars($user['barangay_id'] ?? '') ?>"
        data-date="<?= date('Y-m-d', strtotime($user['created_at'])) ?>">
    <div>
        <span class="mobile-label">ID:</span>
        <?= $user['id'] ?>
    </div>
    <div>
        <span class="mobile-label">Name:</span>
        <?= htmlspecialchars($full_name) ?>
    </div>
    <div>
        <span class="mobile-label">Birthday:</span>
        <?= !empty($user['birthday']) ? date('F j, Y', strtotime($user['birthday'])) : 'N/A' ?>
    </div>
    <div>
        <span class="mobile-label">Phone:</span>
        <?= htmlspecialchars($phone_number) ?>
    </div>
    <div>
        <span class="mobile-label">Created By:</span>
        <?= htmlspecialchars($user['created_by'] ?? 'System') ?>
    </div>
    <div>
        <span class="mobile-label">Date Created:</span>
        <?= date('F j, Y', strtotime($user['created_at'])) ?>
    </div>
    <div class="action-buttons">
        <button class="btn btn-edit edit-btn" data-id="<?= $user['id'] ?>">
            <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
        </button>
        <button class="btn btn-delete delete-btn" data-id="<?= $user['id'] ?>">
            <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
        </button>
    </div>
</div>
<?php endwhile; ?>
                    </div>
                <?php endif; ?>
                
                <!-- No results message (initially hidden) -->
                <div class="no-users" id="noResultsMessage" style="display: none;">
                    <i class="fas fa-users"></i>
                    <h4>No Matching Residents</h4>
                    <p>No residents match your current search.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewUserModalLabel">Resident Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Name:</strong>
                    </div>
                    <div class="col-md-9" id="view_name"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Birthday:</strong>
                    </div>
                    <div class="col-md-9" id="view_birthday"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Age:</strong>
                    </div>
                    <div class="col-md-9" id="view_age"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Phone:</strong>
                    </div>
                    <div class="col-md-9" id="view_phone"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Complete Address:</strong>
                    </div>
                    <div class="col-md-9" id="view_address"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Barangay:</strong>
                    </div>
                    <div class="col-md-9" id="view_barangay"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Created By:</strong>
                    </div>
                    <div class="col-md-9" id="view_created_by"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Date Created:</strong>
                    </div>
                    <div class="col-md-9" id="view_created_at"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Resident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="userFirstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="userFirstName" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="userMiddleName" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="userMiddleName">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="userLastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="userLastName" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="userBirthday" class="form-label">Birthday</label>
                            <input type="date" class="form-control" id="userBirthday" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="userPhone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="tel" class="form-control" id="userPhone" 
                                    placeholder="9123456789" pattern="\d{10}" maxlength="10" required
                                    title="Please enter a valid 10-digit mobile number (e.g., 9123456789)">
                            </div>
                            <small class="form-text text-muted">Enter 10-digit mobile number after +63 (e.g. 9123456789)</small>
                            <div class="invalid-feedback" id="phoneError" style="display: none;">
                                Please enter exactly 10 digits after +63
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="userAddress" class="form-label">Complete Address</label>
                        <textarea class="form-control" id="userAddress" rows="3" required></textarea>
                    </div>
                    <!-- In index.php, replace the alert in the user modal -->
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> A temporary 8-character password will be generated and sent to the user's phone number.
</div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-save" id="saveUserBtn">Save</button>
            </div>
        </div>
    </div>
</div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="delete_user_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! Are you absolutely sure you want to delete this resident account?
                        </div>
                        <div class="mb-3">
                            <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                            <input type="text" class="form-control" id="confirmDelete" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_user" id="submitDelete" disabled>Delete Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Create Modal -->
    <div class="modal fade" id="successCreateModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Resident Added</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Resident Account Created Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterCreate">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Edit Modal -->
    <div class="modal fade" id="successEditModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Resident Updated</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Resident Account Updated Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterEdit">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Delete Modal -->
    <div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Resident Deleted</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Resident Account Deleted Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterDelete">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
$(document).ready(function() {
    let currentUserId = null;
    let isEditingAdmin = false;
    const userModal = new bootstrap.Modal('#userModal');
    const deleteModal = new bootstrap.Modal('#deleteModal');
    const successCreateModal = new bootstrap.Modal('#successCreateModal');
    const successEditModal = new bootstrap.Modal('#successEditModal');
    const successDeleteModal = new bootstrap.Modal('#successDeleteModal');
    
    // Check for success messages in URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('created')) {
        successCreateModal.show();
    } else if (urlParams.has('updated')) {
        successEditModal.show();
    } else if (urlParams.has('deleted')) {
        successDeleteModal.show();
    }
    
    // Continue buttons for success modals
    $('#continueAfterCreate, #continueAfterEdit, #continueAfterDelete').click(function() {
        window.location.href = window.location.pathname; // Reload without query params
    });
    
    // Add User
    $('#addUserBtn').click(function() {
        isEditingAdmin = false;
        $('#modalTitle').text('Add Resident');
        $('#userForm')[0].reset();
        $('#userId').val('');
        $('#userPassword').attr('placeholder', '').attr('required', 'true');
        $('#userPhone').removeClass('is-invalid');
        $('#phoneError').hide();
        
        userModal.show();
    });

// View user details (using data from table row)
$(document).on('click', '.view-btn', function() {
    const row = $(this).closest('.user-row');
    
    // Get data from row attributes
    const firstName = row.data('first-name');
    const middleName = row.data('middle-name');
    const lastName = row.data('last-name');
    const birthday = row.data('birthday');
    const address = row.data('address');
    const phone = row.data('phone');
    
    // Format the name
    const fullName = `${firstName} ${middleName || ''} ${lastName}`.trim();
    
    // Calculate age
    const age = birthday ? calculateAge(birthday) : 'N/A';
    
    // Update modal content
    $('#view_name').text(fullName);
    $('#view_birthday').text(birthday ? formatDate(birthday) : 'N/A');
    $('#view_age').text(age !== 'N/A' ? `${age} years old` : 'N/A');
    $('#view_phone').text(formatPhoneNumber(phone));
    $('#view_address').text(address || 'N/A');
    $('#view_barangay').text('<?= htmlspecialchars($barangay_name) ?>');
    
    // Get created by and date from table cells
    if ($(this).closest('tr').length) { // Desktop table
        $('#view_created_by').text($(this).closest('tr').find('td:eq(2)').text() || 'System');
        $('#view_created_at').text($(this).closest('tr').find('td:eq(3)').text());
    } else { // Mobile table
        const mobileRow = $(this).closest('.mobile-row');
        $('#view_created_by').text(mobileRow.find('div:nth-child(5)').text().replace('Created By:', '').trim() || 'System');
        $('#view_created_at').text(mobileRow.find('div:nth-child(6)').text().replace('Date Created:', '').trim());
    }
    
    $('#viewUserModal').modal('show');
});

// Function to calculate age from birthday
function calculateAge(birthday) {
    if (!birthday) return 'N/A';
    
    const birthDate = new Date(birthday);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
}

// Function to format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// Function to format phone number
function formatPhoneNumber(phone) {
    if (!phone) return 'N/A';
    
    if (phone.startsWith('+63')) {
        return '0' + phone.substring(3);
    }
    return phone;
}
    
    // Edit user or admin
$(document).on('click', '.edit-btn', function() {
    const userId = $(this).data('id');
    const row = $(this).closest('.user-row');
    
    // Check if we're editing the admin account (the one at the top)
    isEditingAdmin = $(this).closest('.recent-activity-card').find('.card-header h4').text() === 'My Admin Account';
    
    $('#modalTitle').text(isEditingAdmin ? 'Edit Admin' : 'Edit Resident');
    $('#userId').val(userId);
    $('#userFirstName').val(row.data('first-name'));
    $('#userMiddleName').val(row.data('middle-name'));
    $('#userLastName').val(row.data('last-name'));
    $('#userBirthday').val(row.data('birthday'));
    $('#userAddress').val(row.data('address'));
    
    // Extract phone number (remove +63 if present)
    let phone = row.data('phone');
    if (phone.startsWith('0')) {
        phone = phone.substring(1);
    }
    $('#userPhone').val(phone);
    
    $('#userPhone').removeClass('is-invalid');
    $('#phoneError').hide();
    userModal.show();
});
        
    // Save user or admin (add/edit)
$('#saveUserBtn').click(function() {
    const form = $('#userForm')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Additional validation for phone number
    const phoneInput = $('#userPhone');
    const phoneValue = phoneInput.val();
    if (phoneValue.length !== 10 || !/^\d+$/.test(phoneValue)) {
        phoneInput.addClass('is-invalid');
        $('#phoneError').show();
        phoneInput.focus();
        return;
    } else {
        phoneInput.removeClass('is-invalid');
        $('#phoneError').hide();
    }
    
    const userId = $('#userId').val();
    const firstName = $('#userFirstName').val().trim();
    const middleName = $('#userMiddleName').val().trim();
    const lastName = $('#userLastName').val().trim();
    const phone = '+63' + phoneValue; // Format as +63
    const department_id = <?= $department_id ?>; // Use the admin's department_id
    const birthday = $('#userBirthday').val();
    const address = $('#userAddress').val();
    
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
    
    // Determine which endpoint to use
    const endpoint = isEditingAdmin ? 'save_admin.php' : 'save_user.php';
    
    $.ajax({
        url: endpoint,
        type: 'POST',
        dataType: 'json',
        data: {
            id: userId,
            name: firstName,
            middle_name: middleName,
            last_name: lastName,
            phone: phone,
barangay_id: <?= $barangay_id ?>,
            birthday: birthday,
            address: address
        },
        success: function(response) {
    if (response.success) {
        userModal.hide();
        if (userId) {
            // Edit operation
            window.location.href = window.location.pathname + '?updated=1';
        } else {
            // Create operation - show a message about the password being sent
            if (response.message.includes('failed to send SMS')) {
                alert('User created but SMS failed. Please notify the user of their password manually.');
            }
            window.location.href = window.location.pathname + '?created=1';
        }
    } else {
                // Show error modal instead of alert
                const errorModal = new bootstrap.Modal(document.createElement('div'));
                $('body').append(`
                    <div class="modal fade" id="errorModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Error</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    ${response.message || 'Failed to save account'}
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                $('#errorModal').modal('show');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error, xhr.responseText);
            // Show error modal instead of alert
            const errorModal = new bootstrap.Modal(document.createElement('div'));
            $('body').append(`
                <div class="modal fade" id="errorModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Error</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Error saving account: ${xhr.responseJSON?.message || xhr.responseText || 'Unknown error'}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            $('#errorModal').modal('show');
        },
        complete: function() {
            $btn.prop('disabled', false).text('Save');
        }
    });
});
    
    // Delete user
    $(document).on('click', '.delete-btn', function() {
        currentUserId = $(this).data('id');
        $('#delete_user_id').val(currentUserId);
        $('#confirmDelete').val(''); // Clear previous input
        $('#submitDelete').prop('disabled', true);
        deleteModal.show();
    });

    // Delete confirmation validation
    $('#confirmDelete').on('input', function() {
        $('#submitDelete').prop('disabled', $(this).val().toUpperCase() !== 'DELETE');
    });

    // Confirm delete
    $(document).on('submit', '#deleteModal form', function(e) {
        e.preventDefault();
        const $btn = $('#submitDelete');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');
        
        $.ajax({
            url: 'delete_user.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                id: currentUserId,
                delete_user: true // This indicates the form was submitted
            },
            success: function(response) {
                if (response.success) {
                    deleteModal.hide();
                    window.location.href = window.location.pathname + '?deleted=1';
                } else {
                    alert(response.message || 'Failed to delete user');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error, xhr.responseText);
                alert('Error deleting user: ' + (xhr.responseJSON?.message || xhr.responseText || 'Unknown error'));
            },
            complete: function() {
                $btn.prop('disabled', false).text('Delete Account');
            }
        });
    });
    
    
    // Live filtering for users (excludes admin account)
    function applyUserFilters() {
        const searchTerm = $('#searchUsersFilter').val().toLowerCase();
        const dateFilter = $('#dateUsersFilter').val();
        
        let hasResults = false;
        
        // Target all user rows except admin account
        $('.user-row').each(function() {
            const $row = $(this);
            
            // Skip the admin account row (always show it)
            if ($row.closest('.recent-activity-card').find('.card-header h4').text() === 'My Admin Account') {
                $row.show();
                return true; // continue to next iteration
            }
            
            const id = $row.data('id').toString();
            const name = $row.data('name');
            const phone = $row.data('phone');
            const date = $row.data('date');
            
            // Check search term match
            const searchMatch = searchTerm === '' || 
                name.includes(searchTerm) || 
                id.includes(searchTerm) || 
                phone.includes(searchTerm);
            
            // Check date filter match
            const dateMatch = dateFilter === '' || date === dateFilter;
            
            // Show/hide based on all filters
            if (searchMatch && dateMatch) {
                $row.show();
                hasResults = true;
            } else {
                $row.hide();
            }
        });
        
        // Show/hide no results message
        if (hasResults || ($('#searchUsersFilter').val() === '' && $('#dateUsersFilter').val() === '')) {
            $('#noResultsMessage').hide();
        } else {
            $('#noResultsMessage').show();
        }
    }
    
    // Apply filters when any filter changes
    $('#searchUsersFilter, #dateUsersFilter').on('input change', function() {
        applyUserFilters();
        
        // Update URL with filter parameters without reloading
        const params = new URLSearchParams();
        if ($('#searchUsersFilter').val()) params.set('search', $('#searchUsersFilter').val());
        if ($('#dateUsersFilter').val()) params.set('date', $('#dateUsersFilter').val());
        
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.pushState({}, '', newUrl);
    });

    // Initialize filters from URL parameters on page load
    function initializeFiltersFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('search')) {
            $('#searchUsersFilter').val(urlParams.get('search'));
        }
        if (urlParams.has('date')) {
            $('#dateUsersFilter').val(urlParams.get('date'));
        }
        
        // Apply filters immediately
        applyUserFilters();
    }
    
    // Call the initialization function
    initializeFiltersFromUrl();
});
</script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>