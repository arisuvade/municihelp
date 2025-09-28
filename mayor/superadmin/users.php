<?php
session_start();
include '../../includes/db.php';

// Check if mayor superadmin is logged in
if (!isset($_SESSION['mayor_superadmin_id'])) {
    header("Location: ../../includes/auth/login.php");
    exit();
}

// Get mayor superadmin info
$mayor_superadmin_id = $_SESSION['mayor_superadmin_id'];
$admin_query = $conn->prepare("SELECT name FROM admins WHERE id = ?");
$admin_query->bind_param("i", $mayor_superadmin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();
$admin_data = $admin_result->fetch_assoc();

if (!$admin_data) {
    die("Admin not found");
}

$admin_name = $admin_data['name'] ?? 'Mayor Superadmin';

function formatPhoneNumber($phone) {
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Get all barangays for filter (only departments with parent_id=1 AND excluding IDs 2-6)
$barangays_query = $conn->query("SELECT id, name FROM departments WHERE parent_id = 1 AND id NOT IN (2,3,4,5,6) ORDER BY name");
$barangays = [];
while ($row = $barangays_query->fetch_assoc()) {
    $barangays[$row['id']] = $row['name'];
}

// Filter handling
$where = [];
$params = [];
$types = '';

if (isset($_GET['barangay']) && !empty($_GET['barangay'])) {
    $where[] = "u.department_id = ?";
    $params[] = $_GET['barangay'];
    $types .= 'i';
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(u.name LIKE ? OR u.id = ? OR u.phone LIKE ?)";
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

// Get user accounts with filters
$users_query = $conn->prepare("
    SELECT 
        u.id, 
        u.name,
        u.phone, 
        u.department_id, 
        d.name as barangay_name, 
        a.name as created_by, 
        u.created_at 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN admins a ON u.createdby_admin_id = a.id
    $whereClause
    ORDER BY u.created_at DESC
");

if (!empty($params)) {
    $users_query->bind_param($types, ...$params);
}

$users_query->execute();
$users = $users_query->get_result();

$pageTitle = 'Users List';
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
        
        .table-col-name {
            width: 26%;
        }
        
        .table-col-phone {
            width: 15%;
            text-align: center;
        }
        
        .table-col-barangay {
            width: 15%;
            text-align: center;
        }
        
        .table-col-admin {
            width: 15%;
            text-align: center;
        }
        
        .table-col-date {
            width: 15%;
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
        
        .no-users {
            text-align: center;
            padding: 3rem;
            color: #666;
            background-color: transparent;
            border: none;
            box-shadow: none;
        }
        
        .no-users i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }

        .no-users h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .no-users p {
            font-size: 1rem;
            color: #6c757d;
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
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Users List</h1>
                    <p class="mb-0">View all user accounts</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchFilter" 
                            placeholder="Search by name or phone" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Barangay</label>
                        <select class="form-select" id="barangayFilter">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $id => $name): ?>
                                <option value="<?= $id ?>" <?= isset($_GET['barangay']) && $_GET['barangay'] == $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Created</label>
                        <input type="date" class="form-control" id="dateFilter" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer">
            <?php if ($users->num_rows == 0 && empty($_GET)): ?>
                <div class="no-users">
                    <i class="fas fa-users"></i>
                    <h4>No User Accounts</h4>
                    <p>There are no user accounts in the system.</p>
                </div>
            <?php else: ?>
                <div class="recent-activity-card card">
                    <div class="card-body p-0">
                        <!-- Desktop Table -->
                        <div class="table-responsive desktop-table">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th class="table-col-name">Name</th>
                                        <th class="table-col-phone">Phone</th>
                                        <th class="table-col-barangay">Barangay</th>
                                        <th class="table-col-admin">Created By</th>
                                        <th class="table-col-date">Date Created</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <?php while ($user = $users->fetch_assoc()): 
                                        $phone_number = formatPhoneNumber($user['phone']);
                                    ?>
                                        <tr class="user-row" 
                                            data-id="<?= $user['id'] ?>"
                                            data-name="<?= htmlspecialchars(strtolower($user['name'])) ?>"
                                            data-phone="<?= htmlspecialchars($phone_number) ?>"
                                            data-barangay="<?= htmlspecialchars($user['department_id'] ?? '') ?>"
                                            data-date="<?= date('Y-m-d', strtotime($user['created_at'])) ?>">
                                            <td class="table-col-name"><?= htmlspecialchars($user['name']) ?></td>
                                            <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                            <td class="table-col-barangay"><?= htmlspecialchars($user['barangay_name'] ?? 'N/A') ?></td>
                                            <td class="table-col-admin"><?= htmlspecialchars($user['created_by'] ?? 'System') ?></td>
                                            <td class="table-col-date"><?= date('F j, Y', strtotime($user['created_at'])) ?></td>
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
                            ?>
                                <div class="mobile-row user-row" 
                                    data-id="<?= $user['id'] ?>"
                                    data-name="<?= htmlspecialchars(strtolower($user['name'])) ?>"
                                    data-phone="<?= htmlspecialchars($phone_number) ?>"
                                    data-barangay="<?= htmlspecialchars($user['department_id'] ?? '') ?>"
                                    data-date="<?= date('Y-m-d', strtotime($user['created_at'])) ?>">
                                    <div>
                                        <span class="mobile-label">ID:</span>
                                        <?= $user['id'] ?>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Name:</span>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Phone:</span>
                                        <?= htmlspecialchars($phone_number) ?>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Barangay:</span>
                                        <?= htmlspecialchars($user['barangay_name'] ?? 'N/A') ?>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Created By:</span>
                                        <?= htmlspecialchars($user['created_by'] ?? 'System') ?>
                                    </div>
                                    <div>
                                        <span class="mobile-label">Date Created:</span>
                                        <?= date('F j, Y', strtotime($user['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
$(document).ready(function() {
    // Function to update the URL with filter parameters
    function updateUrl() {
        const params = new URLSearchParams();
        
        const search = $('#searchFilter').val();
        if (search) params.set('search', search);
        
        const barangay = $('#barangayFilter').val();
        if (barangay) params.set('barangay', barangay);
        
        const date = $('#dateFilter').val();
        if (date) params.set('date', date);
        
        if (params.toString()) {
            window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
        } else {
            window.history.replaceState({}, '', window.location.pathname);
        }
    }

    // Function to apply filters
    function applyFilters() {
        const searchTerm = $('#searchFilter').val().toLowerCase();
        const barangayFilter = $('#barangayFilter').val();
        const dateFilter = $('#dateFilter').val();
        
        let anyVisible = false;
        let totalRows = 0;

        $('.user-row').each(function() {
            totalRows++;
            const $row = $(this);
            const id = $row.data('id').toString();
            const name = $row.data('name');
            const phone = $row.data('phone');
            const barangay = $row.data('barangay').toString();
            const date = $row.data('date');
            
            const matchesSearch = !searchTerm || 
                name.includes(searchTerm) || 
                id.includes(searchTerm) || 
                phone.includes(searchTerm);
            
            const matchesBarangay = !barangayFilter || barangay === barangayFilter;
            const matchesDate = !dateFilter || date === dateFilter;
            
            if (matchesSearch && matchesBarangay && matchesDate) {
                $row.show();
                anyVisible = true;
            } else {
                $row.hide();
            }
        });

        // Show/hide no results message
        const $noResults = $('.no-users-filtered');
        
        if (!anyVisible) {
            // If there are no users at all (initial state)
            if (totalRows === 0 && $('.no-users').length) {
                // Show the initial "no users" message
                $('.no-users').show();
                $('.recent-activity-card').hide();
            } else if (totalRows > 0) {
                // Only show filtered message if we have users but none match filters
                if ($noResults.length === 0) {
                    $('#resultsContainer').prepend(`
                        <div class="no-users no-users-filtered">
                            <i class="fas fa-users"></i>
                            <h4>No Matching Users</h4>
                            <p>No users match your current filters.</p>
                        </div>
                    `);
                }
                
                // Hide tables
                $('.recent-activity-card').hide();
            }
        } else {
            // Remove filtered message if it exists
            $noResults.remove();
            
            // Show tables
            $('.recent-activity-card').show();
            
            // Hide initial "no users" message if it exists
            $('.no-users').hide();
        }
    }

    // Event listeners for filters
    $('#searchFilter, #barangayFilter, #dateFilter').on('input change', function() {
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