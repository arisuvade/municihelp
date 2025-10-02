<?php
session_start();
include '../../includes/db.php';

// Check if superadmin is logged in
if (!isset($_SESSION['mayor_superadmin_id'])) {
    header("Location: ../../includes/auth/login.php");
    exit();
}

// Get superadmin info
$mayor_superadmin_id = $_SESSION['mayor_superadmin_id'];
$superadmin_query = $conn->query("SELECT name, department_id FROM admins WHERE id = $mayor_superadmin_id");
$superadmin_data = $superadmin_query->fetch_assoc();
$superadmin_name = $superadmin_data['name'] ?? 'Mayor SuperAdmin';
$superadmin_dept_id = $superadmin_data['department_id'];

// Get all departments for filter (IDs 2-6 only)
$departments_query = $conn->query("SELECT id, name FROM departments WHERE id >= 2 AND id <= 6 ORDER BY name");
$departments_filter = [];
while ($row = $departments_query->fetch_assoc()) {
    $departments_filter[$row['id']] = $row['name'];
}

// Get all barangays for filter (IDs 17-35 only)
$barangays_query = $conn->query("SELECT id, name FROM departments WHERE id >= 17 AND id <= 35 ORDER BY name");
$barangays_filter = [];
while ($row = $barangays_query->fetch_assoc()) {
    $barangays_filter[$row['id']] = $row['name'];
}

// Filter handling
$where = [];
$params = [];
$types = '';

if (isset($_GET['department']) && !empty($_GET['department'])) {
    $where[] = "a.department_id = ?";
    $params[] = $_GET['department'];
    $types .= 'i';
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(a.name LIKE ? OR a.phone LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $where[] = "DATE(a.created_at) = ?";
    $params[] = $_GET['date'];
    $types .= 's';
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$pageTitle = 'Admin Management';
include '../../includes/header.php';

function formatPhoneNumber($phone) {
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Get all admin accounts with their department info (excluding superadmins with IDs 1 and 2 AND only departments with parent_id=1)
$admins_query = $conn->prepare("
    SELECT a.id, a.name, a.phone, a.department_id, d.name as department_name, a.created_at 
    FROM admins a 
    JOIN departments d ON a.department_id = d.id
    $whereClause AND a.id NOT IN (1, 2) AND d.parent_id = 1
    ORDER BY a.created_at DESC
");

if (!empty($params)) {
    $admins_query->bind_param($types, ...$params);
}

$admins_query->execute();
$admins = $admins_query->get_result();

// Get all departments for the edit form (IDs 2-6 and 17-35 only, excluding ID 1)
$departments = $conn->query("SELECT id, name FROM departments WHERE id IN (2,3,4,5,6,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35) ORDER BY name");
$departmentsArray = $departments->fetch_all(MYSQLI_ASSOC);
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
        
        .superadmin-badge {
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
        
        .superadmin-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
        }
        
        .superadmin-badge i {
            margin-right: 8px;
            filter: brightness(0) invert(1);
        }
        
        .filter-card {
            margin-bottom: 1.5rem;
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
            width: 25%;
        }
        
        .table-col-phone {
            width: 20%;
            text-align: center;
        }
        
        .table-col-department {
            width: 25%;
            text-align: center;
        }
        
        .table-col-date {
            width: 15%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 10%;
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

        /* Button styles */
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
        
        .no-admins {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-admins i {
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
            
            .superadmin-badge {
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
            
            .filter-group {
                min-width: 100%;
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
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* No results message */
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
            display: none;
        }
        
        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
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
        
        .optgroup-header {
            font-weight: bold;
            font-style: normal;
            background-color: #f8f9fa;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Mayor's Office Admin Management</h1>
                    <p class="mb-0">Manage admin accounts and permissions</p>
                </div>
                <div class="superadmin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($superadmin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Action Header -->
        <div class="action-header">
            <h2 class="mb-0">Accounts List</h2>
            <button class="btn btn-add" id="addAdminBtn">
                <i class="fas fa-plus"></i> Add Admin
            </button>
        </div>
        
        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchFilter" 
                            placeholder="Search by name or phone" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Department/Barangay</label>
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments/Barangays</option>
                            <optgroup label="Departments">
                                <option value="all_departments" <?= isset($_GET['department']) && $_GET['department'] == 'all_departments' ? 'selected' : '' ?>>All Departments</option>
                                <?php foreach ($departments_filter as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= isset($_GET['department']) && $_GET['department'] == $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Barangays">
                                <option value="all_barangays" <?= isset($_GET['department']) && $_GET['department'] == 'all_barangays' ? 'selected' : '' ?>>All Barangays</option>
                                <?php foreach ($barangays_filter as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= isset($_GET['department']) && $_GET['department'] == $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date Created</label>
                        <input type="date" class="form-control" id="dateFilter" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Accounts Section -->
        <?php
        $admins_count = $admins->num_rows;
        ?>
        
        <div class="recent-activity-card card mb-4" id="adminsContainer" style="<?= ($admins_count == 0 && !empty($_GET)) ? 'display: none;' : '' ?>">
            <div class="card-header">
                <h4 class="mb-0">Admin Accounts</h4>
            </div>
            <div class="card-body p-0">
                <?php if ($admins_count == 0): ?>
                    <div class="no-admins">
                        <i class="fas fa-users"></i>
                        <h4>No Admin Accounts</h4>
                        <p>There are no admin accounts to display.</p>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table -->
                    <div class="table-responsive desktop-table">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th class="table-col-name">Name</th>
                                    <th class="table-col-phone">Phone</th>
                                    <th class="table-col-department">Department/Barangay</th>
                                    <th class="table-col-date">Date Created</th>
                                    <th class="table-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody id="adminsTableBody">
                                <?php while ($admin = $admins->fetch_assoc()): 
                                    $phone_number = formatPhoneNumber($admin['phone']);
                                    $is_department = ($admin['department_id'] >= 2 && $admin['department_id'] <= 6);
                                ?>
                                    <tr class="admin-row" 
                                        data-name="<?= htmlspecialchars(strtolower($admin['name'])) ?>"
                                        data-phone="<?= htmlspecialchars($phone_number) ?>"
                                        data-department="<?= $admin['department_id'] ?>"
                                        data-date="<?= date('Y-m-d', strtotime($admin['created_at'])) ?>"
                                        data-type="<?= $is_department ? 'department' : 'barangay' ?>">
                                        <td class="table-col-name"><?= htmlspecialchars($admin['name']) ?></td>
                                        <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                        <td class="table-col-department"><?= htmlspecialchars($admin['department_name']) ?></td>
                                        <td class="table-col-date"><?= date('F j, Y', strtotime($admin['created_at'])) ?></td>
                                        <td class="table-col-actions">
                                            <div class="action-buttons">
                                                <button class="btn btn-edit edit-btn" data-id="<?= $admin['id'] ?>">
                                                    <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                                </button>
                                                <button class="btn btn-delete delete-btn" data-id="<?= $admin['id'] ?>">
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
                    <div class="mobile-table" id="mobileAdminsTable">
                        <?php 
                        $admins->data_seek(0);
                        while ($admin = $admins->fetch_assoc()): 
                            $phone_number = formatPhoneNumber($admin['phone']);
                            $is_department = ($admin['department_id'] >= 2 && $admin['department_id'] <= 6);
                        ?>
                            <div class="mobile-row admin-row" 
                                data-name="<?= htmlspecialchars(strtolower($admin['name'])) ?>"
                                data-phone="<?= htmlspecialchars($phone_number) ?>"
                                data-department="<?= $admin['department_id'] ?>"
                                data-date="<?= date('Y-m-d', strtotime($admin['created_at'])) ?>"
                                data-type="<?= $is_department ? 'department' : 'barangay' ?>">
                                <div>
                                    <span class="mobile-label">Name:</span>
                                    <?= htmlspecialchars($admin['name']) ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Phone:</span>
                                    <?= htmlspecialchars($phone_number) ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Department/Barangay:</span>
                                    <?= htmlspecialchars($admin['department_name']) ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Date Created:</span>
                                    <?= date('F j, Y', strtotime($admin['created_at'])) ?>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn btn-edit edit-btn" data-id="<?= $admin['id'] ?>">
                                        <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                    </button>
                                    <button class="btn btn-delete delete-btn" data-id="<?= $admin['id'] ?>">
                                        <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- No results message (shown when no results during search) -->
        <div class="no-admins" id="noResultsMessage" style="display: none;">
            <i class="fas fa-users"></i>
            <h4>No Matching Admins</h4>
            <p>No admins match your current filters.</p>
        </div>

    <!-- Add/Edit Admin Modal -->
    <div class="modal fade" id="adminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="adminForm">
                        <input type="hidden" id="adminId">
                        <div class="mb-3">
                            <label for="adminName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="adminName" required>
                        </div>
                        <div class="mb-3">
                            <label for="adminPhone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="tel" class="form-control" id="adminPhone" 
                                    placeholder="9123456789" pattern="\d{10}" maxlength="10" required
                                    title="Please enter a valid 10-digit mobile number (e.g., 9123456789)">
                            </div>
                            <small class="form-text text-muted">Enter 10-digit mobile number after +63 (e.g. 9123456789)</small>
                            <div class="invalid-feedback" id="phoneError" style="display: none;">
                                Please enter exactly 10 digits after +63
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="adminDepartment" class="form-label">Department/Barangay</label>
                            <select class="form-select" id="adminDepartment" required>
                                <option value="" selected disabled>Select Department/Barangay</option>
                                <optgroup label="Departments">
                                    <?php foreach ($departmentsArray as $dept): ?>
                                        <?php if ($dept['id'] >= 2 && $dept['id'] <= 6): ?>
                                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Barangays">
                                    <?php foreach ($departmentsArray as $dept): ?>
                                        <?php if ($dept['id'] < 2 || $dept['id'] > 6): ?>
                                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="adminPassword" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="adminPassword" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Password is required
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-save" id="saveAdminBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="delete_admin_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! Are you absolutely sure you want to delete this admin account?
                        </div>
                        <div class="mb-3">
                            <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                            <input type="text" class="form-control" id="confirmDelete" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_admin" id="submitDelete" disabled>Delete Admin</button>
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
                    <h5 class="modal-title">Admin Created</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Admin Account Created Successfully!</h4>
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
                    <h5 class="modal-title">Admin Updated</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Admin Account Updated Successfully!</h4>
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
                    <h5 class="modal-title">Admin Deleted</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Admin Account Deleted Successfully!</h4>
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
    let currentAdminId = null;
    const adminModal = new bootstrap.Modal('#adminModal');
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
    
    // Toggle password visibility
    $('#togglePassword').click(function() {
        const passwordInput = $('#adminPassword');
        const icon = $(this).find('i');
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
    
    // Add Admin
    $('#addAdminBtn').click(function() {
        $('#modalTitle').text('Add Admin');
        $('#adminForm')[0].reset();
        $('#adminId').val('');
        $('#adminPassword').attr('placeholder', '').attr('required', 'true');
        $('#adminPhone').removeClass('is-invalid');
        $('#phoneError').hide();
        
        // Reset the department dropdown
        const deptSelect = $('#adminDepartment');
        deptSelect.val(''); // Reset to default "Select Department/Barangay" option
        
        adminModal.show();
    });
    
    // Edit admin
    $(document).on('click', '.edit-btn', function() {
        const adminId = $(this).data('id');
        const row = $(this).closest('.admin-row');
        
        $('#modalTitle').text('Edit Admin');
        $('#adminId').val(adminId);
        $('#adminName').val(row.find('td:nth-child(1)').text());
        
        // Extract phone number (remove +63 if present)
        let phone = row.find('td:nth-child(2)').text();
        if (phone.startsWith('0')) {
            phone = phone.substring(1);
        }
        $('#adminPhone').val(phone);
        
        // Set department
        const deptName = row.find('td:nth-child(3)').text();
        const deptSelect = $('#adminDepartment');
        
        // Select the current department
        deptSelect.find('option').each(function() {
            if ($(this).text() === deptName) {
                $(this).prop('selected', true);
                return false;
            }
        });
        
        $('#adminPassword').val('').removeAttr('required').attr('placeholder', 'Leave blank to keep current password');
        $('#adminPhone').removeClass('is-invalid');
        $('#phoneError').hide();
        adminModal.show();
    });
        
    // Save admin (add/edit)
    $('#saveAdminBtn').click(function() {
        const form = $('#adminForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Additional validation for phone number
        const phoneInput = $('#adminPhone');
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
        
        const adminId = $('#adminId').val();
        const name = $('#adminName').val().trim();
        const phone = '+63' + phoneValue; // Format as +63
        const departmentId = $('#adminDepartment').val();
        const password = $('#adminPassword').val();
        
        // For edit, password is not required if left blank
        if (adminId && password === '') {
            // Proceed without password change
        } else if (password === '') {
            // Create error modal for missing password
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
                                Password is required for new admin accounts.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            $('#errorModal').modal('show');
            return;
        }
        
        // Validate password length if provided (minimum 8 characters)
        if (password && password.length < 8) {
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
                                Password must be at least 8 characters long.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            $('#errorModal').modal('show');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
        
        $.ajax({
            url: 'save_admin.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: adminId,
                name: name,
                phone: phone,
                department_id: departmentId,
                password: password
            },
            success: function(response) {
                if (response.success) {
                    adminModal.hide();
                    if (adminId) {
                        // Edit operation
                        window.location.href = window.location.pathname + '?updated=1';
                    } else {
                        // Create operation
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
                                        ${response.message || 'Failed to save admin'}
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
                                    Error saving admin: ${xhr.responseJSON?.message || xhr.responseText || 'Unknown error'}
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
    
    // Delete admin
    $(document).on('click', '.delete-btn', function() {
        currentAdminId = $(this).data('id');
        $('#delete_admin_id').val(currentAdminId);
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
            url: 'delete_admin.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                id: currentAdminId,
                delete_admin: true // This indicates the form was submitted
            },
            success: function(response) {
                if (response.success) {
                    deleteModal.hide();
                    window.location.href = window.location.pathname + '?deleted=1';
                } else {
                    alert(response.message || 'Failed to delete admin');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error, xhr.responseText);
                alert('Error deleting admin: ' + (xhr.responseJSON?.message || xhr.responseText || 'Unknown error'));
            },
            complete: function() {
                $btn.prop('disabled', false).text('Delete Admin');
            }
        });
    });
    
    // Function to update the URL with filter parameters
    function updateUrl() {
        const params = new URLSearchParams();
        
        const search = $('#searchFilter').val();
        if (search) params.set('search', search);
        
        const department = $('#departmentFilter').val();
        if (department) params.set('department', department);
        
        const date = $('#dateFilter').val();
        if (date) params.set('date', date);
        
        // Only update URL if we have filters to avoid unnecessary reloads
        if (params.toString()) {
            window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
        } else {
            window.history.replaceState({}, '', window.location.pathname);
        }
    }

    // Filter function
    function applyFilters() {
        const searchTerm = $('#searchFilter').val().toLowerCase();
        const departmentFilter = $('#departmentFilter').val();
        const dateFilter = $('#dateFilter').val();
        
        let anyVisible = false;
        let totalRows = 0;

        // Filter admins
        $('.admin-row').each(function() {
            totalRows++;
            const $row = $(this);
            const name = $row.data('name');
            const phone = $row.data('phone');
            const department = $row.data('department').toString();
            const date = $row.data('date');
            const type = $row.data('type');
            
            const matchesSearch = !searchTerm || 
                name.includes(searchTerm) || 
                phone.includes(searchTerm);
            
            let matchesDepartment = true;
            if (departmentFilter) {
                if (departmentFilter === 'all_departments') {
                    matchesDepartment = (type === 'department');
                } else if (departmentFilter === 'all_barangays') {
                    matchesDepartment = (type === 'barangay');
                } else {
                    matchesDepartment = (department === departmentFilter);
                }
            }
            
            const matchesDate = !dateFilter || date === dateFilter;
            
            if (matchesSearch && matchesDepartment && matchesDate) {
                $row.show();
                anyVisible = true;
            } else {
                $row.hide();
            }
        });

        // Show/hide sections based on results
        const adminsContainer = $('#adminsContainer');
        const noResultsMsg = $('#noResultsMessage');
        
        if (anyVisible) {
            adminsContainer.show();
        } else {
            adminsContainer.hide();
        }
        
        // Show no results message if no results during search
        if (!anyVisible && (searchTerm || departmentFilter || dateFilter)) {
            noResultsMsg.show();
        } else {
            noResultsMsg.hide();
        }
    }

    // Event listeners for filters
    $('#searchFilter, #departmentFilter, #dateFilter').on('input change', function() {
        updateUrl();
        applyFilters();
    });

    // Initial filter application
    applyFilters();
});
</script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>