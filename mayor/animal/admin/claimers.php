<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['animal_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$animal_admin_id = $_SESSION['animal_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $animal_admin_id");
$admin_data = $admin_query->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

// Get barangays for dropdown
$barangays = [];
$barangay_result = $conn->query("SELECT id, name FROM barangays ORDER BY name");
if ($barangay_result) {
    $barangays = $barangay_result->fetch_all(MYSQLI_ASSOC);
}

// Handle AJAX search requests
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    
    try {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $claims = isset($_GET['claims']) ? trim($_GET['claims']) : '';
        $barangay = isset($_GET['barangay']) ? intval($_GET['barangay']) : 0;
        
        $query = "SELECT c.id, c.first_name, c.middle_name, c.last_name, c.birthday, 
                         c.barangay_id, c.total_claims, br.name as barangay_name
                  FROM dog_claimers c
                  JOIN barangays br ON c.barangay_id = br.id
                  WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $query .= " AND (c.first_name LIKE CONCAT('%', ?, '%') 
                          OR c.last_name LIKE CONCAT('%', ?, '%') 
                          OR c.id = ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = is_numeric($search) ? $search : 0;
            $types .= 'ssi';
        }
        
        if (!empty($claims) && is_numeric($claims)) {
            $query .= " AND c.total_claims = ?";
            $params[] = (int)$claims;
            $types .= 'i';
        }
        
        if ($barangay > 0) {
            $query .= " AND c.barangay_id = ?";
            $params[] = $barangay;
            $types .= 'i';
        }
        
        $query .= " ORDER BY c.last_name, c.first_name";
        
        $stmt = $conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $claimers = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode($claimers);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

$pageTitle = 'Claimers Management';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_claimer'])) {
        $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
        $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
        $barangay_id = intval($_POST['barangay_id'] ?? 0);
        $total_claims = intval($_POST['total_claims'] ?? 1);
        
        $stmt = $conn->prepare("INSERT INTO dog_claimers (first_name, middle_name, last_name, birthday, barangay_id, total_claims) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $total_claims);
        
        if ($stmt->execute()) {
            header("Location: claimers.php?created=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error adding claimer: ' . $conn->error;
            header("Location: claimers.php");
            exit();
        }
    }
    elseif (isset($_POST['update_claimer'])) {
        $id = intval($_POST['id']);
        $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
        $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
        $barangay_id = intval($_POST['barangay_id'] ?? 0);
        $total_claims = intval($_POST['total_claims'] ?? 1);
        
        $stmt = $conn->prepare("UPDATE dog_claimers SET first_name = ?, middle_name = ?, last_name = ?, birthday = ?, barangay_id = ?, total_claims = ? WHERE id = ?");
        $stmt->bind_param("ssssiii", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $total_claims, $id);
        
        if ($stmt->execute()) {
            header("Location: claimers.php?updated=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error updating claimer: ' . $conn->error;
            header("Location: claimers.php");
            exit();
        }
    }
    elseif (isset($_POST['delete_claimer'])) {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM dog_claimers WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: claimers.php?deleted=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error deleting claimer: ' . $conn->error;
            header("Location: claimers.php");
            exit();
        }
    }
}

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total count
$total_query = "SELECT COUNT(*) as total FROM dog_claimers";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_claimers = $total_row['total'];
$total_pages = ceil($total_claimers / $per_page);

// Get claimers for current page
$query = "SELECT c.id, c.first_name, c.middle_name, c.last_name, c.birthday, 
                 c.barangay_id, c.total_claims, br.name as barangay_name
          FROM dog_claimers c
          JOIN barangays br ON c.barangay_id = br.id
          ORDER BY c.last_name, c.first_name
          LIMIT $per_page OFFSET $offset";
$result = $conn->query($query);
$claimers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

include '../../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="../../../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --approved: #28A745;
            --completed: #4361ee;
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
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filter-card {
            margin-bottom: 1.5rem;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .table-responsive {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            background-color: white;
            overflow-x: auto;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        /* Column widths */
        .table-col-seq {
            width: 5%;
            text-align: center;
        }
        .table-col-name {
            width: 25%;
        }
        
        .table-col-birthday {
            width: 20%;
            text-align: center;
        }
        
        .table-col-barangay {
            width: 15%;
            text-align: center;
        }
        
        .table-col-claims {
            width: 20%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 15%;
            text-align: center;
        }
        
        /* Button styles matching the reference */
        .btn-view {
            background-color: var(--completed);
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
        
        .no-claimers {
            text-align: center;
            padding: 3rem;
            color: #666;
            margin-top: 2rem;
        }
        
        .no-claimers i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        .no-claimers h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-claimers p {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .page-item.active .page-link {
            background-color: var(--munici-green);
            border-color: var(--munici-green);
        }
        
        .page-link {
            color: var(--munici-green);
        }
        
        .fa-spinner {
            color: var(--munici-green);
            margin-bottom: 1rem;
        }
        
        /* Mobile specific styles */
        @media (max-width: 768px) {
            .dashboard-container {
                width: 100%;
                padding: 0 10px;
            }
            
            .dashboard-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }
            
            .admin-badge {
                position: relative;
                right: auto;
                top: auto;
                margin-top: 10px;
                justify-content: center;
                width: 100%;
            }
            
            .action-header h2 {
                font-size: 1.5rem;
            }
            
            .btn-add, .btn-edit, .btn-delete {
                padding: 0.25rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .btn-add i, .btn-edit i, .btn-delete i {
                margin-right: 3px;
            }
            
            .table-col-actions {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .table-col-actions .btn {
                width: 100%;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .pagination .page-item {
                margin: 2px;
            }
            
            .pagination .page-link {
                padding: 0.375rem 0.5rem;
                font-size: 0.875rem;
            }
            
            /* Hide button text on small screens */
            .action-text {
                display: none;
            }
            
            .btn i {
                margin-right: 0 !important;
            }
        }
        
        @media (min-width: 769px) {
            .action-text {
                display: inline;
            }
            
            .btn i {
                margin-right: 5px;
            }
        }
        
        @media (max-width: 576px) {
            .dashboard-header h1 {
                font-size: 1.75rem;
            }
            
            .dashboard-header p {
                font-size: 0.9rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.85rem;
            }
            
            .modal-dialog {
                margin: 0.5rem auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Claimers Management</h1>
                    <p class="mb-0">Manage all dog claimers in the system</p>
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
            <h2 class="mb-0">Claimers List</h2>
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addClaimerModal">
                <i class="fas fa-plus"></i> <span class="action-text">Add Claimer</span>
            </button>
        </div>

        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Search by Name</label>
                        <input type="text" class="form-control" id="searchFilter" placeholder="Search claimers">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Claims Count</label>
                        <input type="number" class="form-control" id="claimsFilter" placeholder="Number of claims">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Barangay</label>
                        <select class="form-select" id="barangayFilter">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?= $barangay['id'] ?>"><?= htmlspecialchars($barangay['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <!-- Claimers List -->
<div id="resultsContainer">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th class="table-col-seq">#</th>
                    <th class="table-col-name">Name</th>
                    <th class="table-col-birthday">Birthday</th>
                    <th class="table-col-barangay">Barangay</th>
                    <th class="table-col-claims">Total Claims</th>
                    <th class="table-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Calculate starting sequence number for this page
                $sequence_number = (($page - 1) * $per_page) + 1;
                
                foreach ($claimers as $claimer): ?>
                    <tr class="claimer-row" 
                        data-id="<?= $claimer['id'] ?>"
                        data-firstname="<?= htmlspecialchars(strtolower($claimer['first_name'])) ?>"
                        data-lastname="<?= htmlspecialchars(strtolower($claimer['last_name'])) ?>"
                        data-barangay="<?= $claimer['barangay_id'] ?>"
                        data-claims="<?= $claimer['total_claims'] ?>">
                        <td class="table-col-seq"><?= $sequence_number ?></td>
                        <td class="table-col-name">
                            <?= htmlspecialchars($claimer['last_name']) ?>, <?= htmlspecialchars($claimer['first_name']) ?>
                            <?= !empty($claimer['middle_name']) ? ' ' . htmlspecialchars($claimer['middle_name'], 0, 1): '' ?>
                        </td>
                        <td class="table-col-birthday" data-birthday="<?= $claimer['birthday'] ?>">
    <?= date('F j, Y', strtotime($claimer['birthday'])) ?>
</td>
                        <td class="table-col-barangay"><?= htmlspecialchars($claimer['barangay_name']) ?></td>
                        <td class="table-col-claims"><?= $claimer['total_claims'] ?></td>
                        <td class="table-col-actions">
                            <button class="btn btn-edit edit-claimer" data-id="<?= $claimer['id'] ?>">
                                <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                            </button>
                            <button class="btn btn-delete delete-claimer" data-id="<?= $claimer['id'] ?>">
                                <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
                            </button>
                        </td>
                    </tr>
                <?php 
                $sequence_number++;
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>

            <?php 
        // Reset sequence number for mobile view
        $sequence_number = 1;
        ?>
            
            <?php if (empty($claimers)): ?>
                <div class="no-claimers">
                    <i class="fas fa-user"></i>
                    <h4>No Claimers Found</h4>
                    <p>There are no claimers to display.</p>
                </div>
            <?php endif; ?>

            
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Determine how many pages to show
                        $max_pages = (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT'])) ? 5 : 10;
                        $start_page = max(1, $page - floor($max_pages / 2));
                        $end_page = min($total_pages, $start_page + $max_pages - 1);
                        
                        // Adjust if we're at the end
                        if ($end_page - $start_page < $max_pages - 1) {
                            $start_page = max(1, $end_page - $max_pages + 1);
                        }
                        
                        // Show first page if not in range
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php 
                        // Show last page if not in range
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'">'.$total_pages.'</a></li>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Claimer Modal -->
    <div class="modal fade" id="addClaimerModal" tabindex="-1" aria-labelledby="addClaimerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addClaimerModalLabel">Add Claimer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="birthday" class="form-label">Birthday</label>
                            <input type="date" class="form-control" id="birthday" name="birthday" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_barangay_id" class="form-label">Barangay</label>
                            <select class="form-select" id="add_barangay_id" name="barangay_id" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?= $barangay['id'] ?>"><?= htmlspecialchars($barangay['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="total_claims" class="form-label">Total Claims</label>
                            <input type="number" class="form-control" id="total_claims" name="total_claims" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_claimer">Add Claimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Claimer Modal -->
    <div class="modal fade" id="editClaimerModal" tabindex="-1" aria-labelledby="editClaimerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="edit_claimer_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editClaimerModalLabel">Edit Claimer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_birthday" class="form-label">Birthday</label>
                            <input type="date" class="form-control" id="edit_birthday" name="birthday" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_barangay_id" class="form-label">Barangay</label>
                            <select class="form-select" id="edit_barangay_id" name="barangay_id" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?= $barangay['id'] ?>"><?= htmlspecialchars($barangay['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_total_claims" class="form-label">Total Claims</label>
                            <input type="number" class="form-control" id="edit_total_claims" name="total_claims" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_claimer">Update Claimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteClaimerModal" tabindex="-1" aria-labelledby="deleteClaimerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="delete_claimer_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteClaimerModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! Are you absolutely sure you want to delete this claimer?
                        </div>
                        <div class="mb-3">
                            <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                            <input type="text" class="form-control" id="confirmDelete" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_claimer" id="submitDelete" disabled>Delete Claimer</button>
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
                    <h5 class="modal-title">Claimer Added</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Claimer Added Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterCreate">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Update Modal -->
    <div class="modal fade" id="successUpdateModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Claimer Updated</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Claimer Updated Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterUpdate">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Delete Modal -->
    <div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Claimer Deleted</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Claimer Deleted Successfully!</h4>
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
            // Check for success messages in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const successCreateModal = new bootstrap.Modal('#successCreateModal');
            const successUpdateModal = new bootstrap.Modal('#successUpdateModal');
            const successDeleteModal = new bootstrap.Modal('#successDeleteModal');

            if (urlParams.has('created')) {
                successCreateModal.show();
            }
            else if (urlParams.has('updated')) {
                successUpdateModal.show();
            }
            else if (urlParams.has('deleted')) {
                successDeleteModal.show();
            }

            // Continue buttons for success modals
            $('#continueAfterCreate, #continueAfterUpdate, #continueAfterDelete').click(function() {
                window.location.href = window.location.pathname; // Reload without query params
            });

            // Edit Claimer
$(document).on('click', '.edit-claimer', function() {
    const claimerId = $(this).data('id');
    const $row = $(this).closest('.claimer-row');
    
    // Get the birthday from the data attribute
    const birthday = $row.find('.table-col-birthday').data('birthday');
    
    $('#edit_claimer_id').val(claimerId);
    $('#edit_first_name').val($row.find('.table-col-name').text().split(',')[1].trim());
    $('#edit_middle_name').val('');
    $('#edit_last_name').val($row.find('.table-col-name').text().split(',')[0].trim());
    $('#edit_birthday').val(birthday); // Set the birthday directly
    $('#edit_barangay_id').val($row.data('barangay'));
    $('#edit_total_claims').val($row.data('claims'));
    
    $('#editClaimerModal').modal('show');
});

            // Delete Claimer
            $(document).on('click', '.delete-claimer', function() {
                const claimerId = $(this).data('id');
                $('#delete_claimer_id').val(claimerId);
                $('#confirmDelete').val(''); // Clear previous input
                $('#submitDelete').prop('disabled', true);
                $('#deleteClaimerModal').modal('show');
            });

            // Delete confirmation validation
            $('#confirmDelete').on('input', function() {
                $('#submitDelete').prop('disabled', $(this).val().toUpperCase() !== 'DELETE');
            });

            // Simple HTML escaping function
            function escapeHtml(text) {
                return text.replace(/&/g, "&amp;")
                           .replace(/</g, "&lt;")
                           .replace(/>/g, "&gt;")
                           .replace(/"/g, "&quot;")
                           .replace(/'/g, "&#039;");
            }

            // Format date for display
            function formatDate(dateString) {
                const date = new Date(dateString);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                return date.toLocaleDateString('en-US', options);
            }

            // Live Filtering with AJAX
            let searchTimer;
            
            function applyFilters() {
                const searchTerm = $('#searchFilter').val().trim();
                const claimsFilter = $('#claimsFilter').val().trim();
                const barangayFilter = $('#barangayFilter').val();
                
                // Only search if there's an active filter
                if (searchTerm || claimsFilter || barangayFilter) {
                    // Show loading indicator
                    $('#resultsContainer').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Searching...</p></div>');
                    
                    // Make AJAX request to search all claimers
                    $.ajax({
                        url: 'claimers.php',
                        data: {
                            ajax_search: 1,
                            search: searchTerm,
                            claims: claimsFilter,
                            barangay: barangayFilter
                        },
                        dataType: 'json',
                        success: function(claimers) {
                            if (claimers.error) {
                                $('#resultsContainer').html(`
                                    <div class="alert alert-danger">
                                        ${claimers.error}
                                    </div>
                                `);
                                return;
                            }
                            
                            if (claimers.length > 0) {
                                // Build the results table
                                let html = `
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="table-col-seq">#</th>
                                                    <th class="table-col-name">Name</th>
                                                    <th class="table-col-birthday">Birthday</th>
                                                    <th class="table-col-barangay">Barangay</th>
                                                    <th class="table-col-claims">Total Claims</th>
                                                    <th class="table-col-actions">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                                
                                let searchSequence = 1;
                                claimers.forEach(claimer => {
                                    html += `
                                        <tr class="claimer-row" 
                                            data-id="${claimer.id}"
                                            data-firstname="${escapeHtml(claimer.first_name).toLowerCase()}"
                                            data-lastname="${escapeHtml(claimer.last_name).toLowerCase()}"
                                            data-barangay="${claimer.barangay_id}"
                                            data-claims="${claimer.total_claims}">
                                            <td class="table-col-seq">${searchSequence}</td>
                                            <td class="table-col-name">
                                                ${escapeHtml(claimer.last_name)}, ${escapeHtml(claimer.first_name)}
                                                ${claimer.middle_name ? ' ' + escapeHtml(claimer.middle_name.substring(0, 1)) + '.' : ''}
                                            </td>
                                            <td class="table-col-birthday">${formatDate(claimer.birthday)}</td>
                                            <td class="table-col-barangay">${escapeHtml(claimer.barangay_name)}</td>
                                            <td class="table-col-claims">${claimer.total_claims}</td>
                                            <td class="table-col-actions">
                                                <button class="btn btn-edit edit-claimer" data-id="${claimer.id}">
                                                    <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                                </button>
                                                <button class="btn btn-delete delete-claimer" data-id="${claimer.id}">
                                                    <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
                                                </button>
                                            </td>
                                        </tr>`;
                                    searchSequence++;
                                });
                                
                                html += `</tbody></table></div>`;
                                
                                $('#resultsContainer').html(html);
                            } else {
                                // Show no results message
                                $('#resultsContainer').html(`
                                    <div class="no-claimers">
                                        <i class="fas fa-user"></i>
                                        <h4>No Claimers Found</h4>
                                        <p>No claimers match your current filters.</p>
                                    </div>
                                `);
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#resultsContainer').html(`
                                <div class="alert alert-danger">
                                    Error loading search results: ${error}. Please try again.
                                </div>
                            `);
                        }
                    });
                } else {
                    // If no filters are active, reload the page to show normal paginated view
                    window.location.href = window.location.pathname;
                }
            }
            
            // Add debounce to prevent too many rapid requests
            $('#searchFilter, #claimsFilter, #barangayFilter').on('input change', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilters, 500);
            });
            
            // Check screen size on load and resize
            function checkScreenSize() {
                if ($(window).width() < 576) {
                    $('.action-text').hide();
                    $('.btn i').css('margin-right', '0');
                } else {
                    $('.action-text').show();
                    $('.btn i').css('margin-right', '5px');
                }
            }
            
            checkScreenSize();
            $(window).resize(checkScreenSize);
        });
    </script>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>