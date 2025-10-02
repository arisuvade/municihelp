<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['mswd_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$mswd_admin_id = $_SESSION['mswd_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $mswd_admin_id");
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
        $barangay = isset($_GET['barangay']) ? intval($_GET['barangay']) : 0;
        $duration = isset($_GET['duration']) ? trim($_GET['duration']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        
        $query = "SELECT b.id, b.first_name, b.middle_name, b.last_name, b.birthday, 
                         b.barangay_id, b.duration, b.status, b.reason, br.name as barangay_name
                  FROM sulong_dulong_beneficiaries b
                  JOIN barangays br ON b.barangay_id = br.id
                  WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $query .= " AND (b.first_name LIKE CONCAT('%', ?, '%') 
                          OR b.last_name LIKE CONCAT('%', ?, '%') 
                          OR b.id = ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = is_numeric($search) ? $search : 0;
            $types .= 'ssi';
        }
        
        if ($barangay > 0) {
            $query .= " AND b.barangay_id = ?";
            $params[] = $barangay;
            $types .= 'i';
        }
        
        if (!empty($duration) && $duration !== 'All') {
            $query .= " AND b.duration = ?";
            $params[] = $duration;
            $types .= 's';
        }
        
        if (!empty($status) && $status !== 'All') {
            $query .= " AND b.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $query .= " ORDER BY b.last_name, b.first_name";
        
        $stmt = $conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $beneficiaries = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode($beneficiaries);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

// Handle AJAX request for student details
if (isset($_GET['ajax_get_student'])) {
    header('Content-Type: application/json');
    
    try {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id > 0) {
            $query = "SELECT b.*, br.name as barangay_name 
                      FROM sulong_dulong_beneficiaries b
                      JOIN barangays br ON b.barangay_id = br.id
                      WHERE b.id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $student = $result->fetch_assoc();
                echo json_encode($student);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Student not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid student ID']);
        }
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

$pageTitle = 'Sulong Dunong Beneficiaries';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
        $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
        $barangay_id = intval($_POST['barangay_id'] ?? 0);
        $duration = $conn->real_escape_string($_POST['duration'] ?? 'Every Month');
        
        // Check if we've reached the limit (800) - only count active students
        $count_result = $conn->query("SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'");
        $count_row = $count_result->fetch_assoc();
        if ($count_row['total'] >= 800) {
            $_SESSION['error_message'] = 'Cannot add more beneficiaries. The limit of 800 active beneficiaries has been reached.';
            header("Location: beneficiaries.php");
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO sulong_dulong_beneficiaries 
                                (first_name, middle_name, last_name, birthday, barangay_id, duration, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("ssssis", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $duration);
        
        if ($stmt->execute()) {
            header("Location: beneficiaries.php?created=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error adding student: ' . $conn->error;
            header("Location: beneficiaries.php");
            exit();
        }
    }
    elseif (isset($_POST['update_student'])) {
        $id = intval($_POST['id']);
        $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
        $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
        $barangay_id = intval($_POST['barangay_id'] ?? 0);
        $duration = $conn->real_escape_string($_POST['duration'] ?? 'Every Month');
        
        $stmt = $conn->prepare("UPDATE sulong_dulong_beneficiaries 
                               SET first_name = ?, middle_name = ?, last_name = ?, 
                                   birthday = ?, barangay_id = ?, duration = ?
                               WHERE id = ?");
        $stmt->bind_param("ssssisi", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $duration, $id);
        
        if ($stmt->execute()) {
            header("Location: beneficiaries.php?updated=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error updating student: ' . $conn->error;
            header("Location: beneficiaries.php");
            exit();
        }
    }
    elseif (isset($_POST['block_student'])) {
        $id = intval($_POST['id']);
        $reason = $conn->real_escape_string($_POST['reason'] ?? '');
        
        $stmt = $conn->prepare("UPDATE sulong_dulong_beneficiaries 
                               SET status = 'Blocked', reason = ? 
                               WHERE id = ?");
        $stmt->bind_param("si", $reason, $id);
        
        if ($stmt->execute()) {
            header("Location: beneficiaries.php?blocked=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error blocking student: ' . $conn->error;
            header("Location: beneficiaries.php");
            exit();
        }
    }
    elseif (isset($_POST['unblock_student'])) {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("UPDATE sulong_dulong_beneficiaries 
                               SET status = 'Active', reason = NULL 
                               WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: beneficiaries.php?unblocked=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error unblocking student: ' . $conn->error;
            header("Location: beneficiaries.php");
            exit();
        }
    }
}

// Get current count of ACTIVE beneficiaries only
$count_result = $conn->query("SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'");
$count_row = $count_result->fetch_assoc();
$current_count = $count_row['total'];

// Pagination - default to showing only active students
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total count of active students for pagination
$total_query = "SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_students = $total_row['total'];
$total_pages = ceil($total_students / $per_page);

// Get students for current page - default to active only
$query = "SELECT b.id, b.first_name, b.middle_name, b.last_name, b.birthday, 
                 b.barangay_id, b.duration, b.status, b.reason, br.name as barangay_name
          FROM sulong_dulong_beneficiaries b
          JOIN barangays br ON b.barangay_id = br.id
          WHERE b.status = 'Active'
          ORDER BY b.last_name, b.first_name
          LIMIT $per_page OFFSET $offset";
$result = $conn->query($query);
$students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
            --blocked: #DC3545;
            --active: #28A745;
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
            width: 20%;
        }
        
        .table-col-birthday {
            width: 15%;
            text-align: center;
        }
        
        .table-col-barangay {
            width: 10%;
            text-align: center;
        }
        
        .table-col-duration {
            width: 15%;
            text-align: center;
        }
        
        .table-col-status {
            width: 10%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 15%;
        }
        
        /* Status badges - matching the reference style */
        .status-badge {
            padding: 0.5em 0.8em;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 50px;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }
        
        .bg-active {
            background-color: var(--approved) !important;
            color: white;
        }
        
        .bg-blocked {
            background-color: var(--declined) !important;
            color: white;
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
        
        .btn-block {
            background-color: var(--blocked);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-block:hover {
            background-color: #c82333;
            color: white;
        }
        
        .btn-unblock {
            background-color: var(--approved);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-unblock:hover {
            background-color: #218838;
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
        
        .no-students {
            text-align: center;
            padding: 3rem;
            color: #666;
            margin-top: 2rem;
        }
        
        .no-students i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        .no-students h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-students p {
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
        
        .limit-counter {
            background-color: var(--munici-green-light);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-weight: 500;
            color: white;
        }
        
        .limit-counter span {
            font-weight: bold;
            color: white;
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
            
            .btn-add, .btn-edit, .btn-block, .btn-unblock, .btn-view {
                padding: 0.25rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .btn-add i, .btn-edit i, .btn-block i, .btn-unblock i, .btn-view i {
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
                    <h1 class="mb-1">Sulong Dunong Beneficiaries</h1>
                    <p class="mb-0">Manage all student beneficiaries in the system</p>
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
            <h2 class="mb-0">Students List</h2>
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-plus"></i> <span class="action-text">Add Student</span>
            </button>
        </div>

        <!-- Limit Counter - Only count active students -->
        <div class="limit-counter">
            Current active beneficiaries: <span><?= $current_count ?></span> / 800
        </div>

        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Search by Name</label>
                        <input type="text" class="form-control" id="searchFilter" placeholder="Search students">
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
                    
                    <div class="filter-group">
                        <label class="form-label">Duration</label>
                        <select class="form-select" id="durationFilter">
                            <option value="All">All Durations</option>
                            <option value="Every Month">Every Month</option>
                            <option value="Per Sem">Per Sem</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="Active" selected>Active</option>
                            <option value="Blocked">Blocked</option>
                            <option value="All">All Status</option>
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

        <!-- Students List -->
        <div id="resultsContainer">
            <?php if (empty($students)): ?>
                 <!-- Only show this message when there are no students at all -->
                <div class="no-students">
                    <i class="fas fa-user-graduate"></i>
                    <h4>No Students Found</h4>
                    <p>There are no students to display.</p>
                </div>
            <?php else: 
        // Initialize sequence number
                        $sequence_number = (($page - 1) * $per_page) + 1;
?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="table-col-seq">#</th>
                                <th class="table-col-name">Name</th>
                                <th class="table-col-birthday">Birthday</th>
                                <th class="table-col-barangay">Barangay</th>
                                <th class="table-col-duration">Duration</th>
                                <th class="table-col-status">Status</th>
                                <th class="table-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr class="student-row" 
                                    data-id="<?= $student['id'] ?>"
                                    data-firstname="<?= htmlspecialchars(strtolower($student['first_name'])) ?>"
                                    data-lastname="<?= htmlspecialchars(strtolower($student['last_name'])) ?>"
                                    data-barangay="<?= $student['barangay_id'] ?>"
                                    data-duration="<?= $student['duration'] ?>"
                                    data-status="<?= $student['status'] ?>">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                    
                                    <td class="table-col-name">
                                        <?= htmlspecialchars($student['last_name']) ?>, <?= htmlspecialchars($student['first_name']) ?>
                                        <?= !empty($student['middle_name']) ? ' ' . htmlspecialchars($student['middle_name'], 0, 1): '' ?>
                                    </td>
                                    <td class="table-col-birthday">
        <?= date('F j, Y', strtotime($student['birthday'])) ?>
    </td>

                                    <td class="table-col-barangay"><?= htmlspecialchars($student['barangay_name']) ?></td>
                                    <td class="table-col-duration"><?= $student['duration'] ?></td>
                                    <td class="table-col-status">
                                        <span class="status-badge <?= $student['status'] === 'Active' ? 'bg-active' : 'bg-blocked' ?>">
                                            <?= $student['status'] ?>
                                        </span>
                                    </td>
                                    <td class="table-col-actions">
                                        <button class="btn btn-view view-student" data-id="<?= $student['id'] ?>">
                                            <i class="fas fa-eye"></i> <span class="action-text">View</span>
                                        </button>
                                        <button class="btn btn-edit edit-student" data-id="<?= $student['id'] ?>">
                                            <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                        </button>
                                        <?php if ($student['status'] === 'Active'): ?>
                                            <button class="btn btn-block block-student" data-id="<?= $student['id'] ?>">
                                                <i class="fas fa-ban"></i> <span class="action-text">Block</span>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-unblock unblock-student" data-id="<?= $student['id'] ?>">
                                                <i class="fas fa-check-circle"></i> <span class="action-text">Unblock</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            $sequence_number++; 
                        endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php 
        // Reset sequence number for mobile view
        $sequence_number = 1;
        ?>
                
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addStudentModalLabel">Add Student</h5>
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
                            <label for="add_duration" class="form-label">Duration</label>
                            <select class="form-select" id="add_duration" name="duration" required>
                                <option value="Every Month">Every Month</option>
                                <option value="Per Sem">Per Sem</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_student">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="edit_student_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
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
                            <label for="edit_duration" class="form-label">Duration</label>
                            <select class="form-select" id="edit_duration" name="duration" required>
                                <option value="Every Month">Every Month</option>
                                <option value="Per Sem">Per Sem</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_student">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Block Confirmation Modal -->
    <div class="modal fade" id="blockStudentModal" tabindex="-1" aria-labelledby="blockStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="block_student_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="blockStudentModalLabel">Confirm Block</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> This will block the student from applying again. Please provide a reason.
                        </div>
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Blocking</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="block_student">Block Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Unblock Confirmation Modal -->
    <div class="modal fade" id="unblockStudentModal" tabindex="-1" aria-labelledby="unblockStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="unblock_student_id">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="unblockStudentModalLabel">Confirm Unblock</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Info:</strong> This will unblock the student, allowing them to apply again.
                        </div>
                        <p>Unblocked students will be marked as "Active" and can apply for benefits again.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" name="unblock_student">Unblock Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Student Modal -->
    <div class="modal fade" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewStudentModalLabel">Student Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>ID:</strong>
                        </div>
                        <div class="col-md-8" id="view_id"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>First Name:</strong>
                        </div>
                        <div class="col-md-8" id="view_first_name"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Middle Name:</strong>
                        </div>
                        <div class="col-md-8" id="view_middle_name"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Last Name:</strong>
                        </div>
                        <div class="col-md-8" id="view_last_name"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Birthday:</strong>
                        </div>
                        <div class="col-md-8" id="view_birthday"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Barangay:</strong>
                        </div>
                        <div class="col-md-8" id="view_barangay"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Duration:</strong>
                        </div>
                        <div class="col-md-8" id="view_duration"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Status:</strong>
                        </div>
                        <div class="col-md-8" id="view_status"></div>
                    </div>
                    <div class="row mb-3" id="reason_row" style="display: none;">
                        <div class="col-md-4">
                            <strong>Block Reason:</strong>
                        </div>
                        <div class="col-md-8" id="view_reason"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Create Modal -->
    <div class="modal fade" id="successCreateModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Student Added</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Student Added Successfully!</h4>
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
                    <h5 class="modal-title">Student Updated</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Student Updated Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterUpdate">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Block Modal -->
    <div class="modal fade" id="successBlockModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Student Blocked</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Student Blocked Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterBlock">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Unblock Modal -->
    <div class="modal fade" id="successUnblockModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Student Unblocked</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Student Unblocked Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterUnblock">Continue</button>
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
            const successBlockModal = new bootstrap.Modal('#successBlockModal');
            const successUnblockModal = new bootstrap.Modal('#successUnblockModal');

            if (urlParams.has('created')) {
                successCreateModal.show();
            }
            else if (urlParams.has('updated')) {
                successUpdateModal.show();
            }
            else if (urlParams.has('blocked')) {
                successBlockModal.show();
            }
            else if (urlParams.has('unblocked')) {
                successUnblockModal.show();
            }

            // Continue buttons for success modals
            $('#continueAfterCreate, #continueAfterUpdate, #continueAfterBlock, #continueAfterUnblock').click(function() {
                window.location.href = window.location.pathname; // Reload without query params
            });

            // Edit Student - FIXED: Using proper event delegation
        $(document).on('click', '.edit-student', function() {
            const studentId = $(this).data('id');
            
            // Make AJAX request to get student details for editing
            $.ajax({
                url: 'beneficiaries.php',
                method: 'GET',
                data: { 
                    ajax_get_student: 1,
                    id: studentId 
                },
                dataType: 'json',
                success: function(student) {
                    if (student.error) {
                        alert('Error: ' + student.error);
                        return;
                    }
                    
                    $('#edit_student_id').val(student.id);
                    $('#edit_first_name').val(student.first_name);
                    $('#edit_middle_name').val(student.middle_name || '');
                    $('#edit_last_name').val(student.last_name);
                    $('#edit_birthday').val(student.birthday);
                    $('#edit_barangay_id').val(student.barangay_id);
                    $('#edit_duration').val(student.duration);
                    
                    $('#editStudentModal').modal('show');
                },
                error: function(xhr, status, error) {
                    alert('Error loading student details: ' + error);
                }
            });
        });

            // Block Student
            $(document).on('click', '.block-student', function() {
                const studentId = $(this).data('id');
                $('#block_student_id').val(studentId);
                $('#blockStudentModal').modal('show');
            });

            // Unblock Student
            $(document).on('click', '.unblock-student', function() {
                const studentId = $(this).data('id');
                $('#unblock_student_id').val(studentId);
                $('#unblockStudentModal').modal('show');
            });

            // View Student Details - Using event delegation
            $(document).on('click', '.view-student', function() {
                const studentId = $(this).data('id');
                
                // Show loading in the modal
                $('#view_id').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_first_name').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_middle_name').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_last_name').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_birthday').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_barangay').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_duration').html('<i class="fas fa-spinner fa-spin"></i>');
                $('#view_status').html('<i class="fas fa-spinner fa-spin"></i>');
                
                $('#viewStudentModal').modal('show');
                
                // Make AJAX request to get student details
                $.ajax({
                    url: 'beneficiaries.php',
                    method: 'GET',
                    data: { 
                        ajax_get_student: 1,
                        id: studentId 
                    },
                    dataType: 'json',
                    success: function(student) {
                        if (student.error) {
                            alert('Error: ' + student.error);
                            $('#viewStudentModal').modal('hide');
                            return;
                        }
                        
                        $('#view_id').text(student.id);
                        $('#view_first_name').text(student.first_name);
                        $('#view_middle_name').text(student.middle_name || 'N/A');
                        $('#view_last_name').text(student.last_name);
                        $('#view_birthday').text(formatDate(student.birthday));
                        $('#view_barangay').text(student.barangay_name);
                        $('#view_duration').text(student.duration);
                        $('#view_status').text(student.status);
                        
                        // Show block reason if student is blocked
                        if (student.status === 'Blocked' && student.reason) {
                            $('#reason_row').show();
                            $('#view_reason').text(student.reason);
                        } else {
                            $('#reason_row').hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error loading student details: ' + error);
                        $('#viewStudentModal').modal('hide');
                    }
                });
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
                const barangayFilter = $('#barangayFilter').val();
                const durationFilter = $('#durationFilter').val();
                const statusFilter = $('#statusFilter').val();
                
                // Only search if there's an active filter
                if (searchTerm || barangayFilter || durationFilter || statusFilter) {
                    // Show loading indicator
                    $('#resultsContainer').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Searching...</p></div>');
                    
                    // Make AJAX request to search all students
                    $.ajax({
                        url: 'beneficiaries.php',
                        data: {
                            ajax_search: 1,
                            search: searchTerm,
                            barangay: barangayFilter,
                            duration: durationFilter,
                            status: statusFilter
                        },
                        dataType: 'json',
                        success: function(students) {
                            if (students.error) {
                                $('#resultsContainer').html(`
                                    <div class="alert alert-danger">
                                        ${students.error}
                                    </div>
                                `);
                                return;
                            }
                            
                            if (students.length > 0) {
                                // Build the results table
                                let html = `
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="table-col-name">Name</th>
                                                    <th class="table-col-birthday">Birthday</th>
                                                    <th class="table-col-barangay">Barangay</th>
                                                    <th class="table-col-duration">Duration</th>
                                                    <th class="table-col-status">Status</th>
                                                    <th class="table-col-actions">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                                
                                students.forEach(student => {
                                    html += `
                                        <tr class="student-row" 
                                            data-id="${student.id}"
                                            data-firstname="${escapeHtml(student.first_name).toLowerCase()}"
                                            data-lastname="${escapeHtml(student.last_name).toLowerCase()}"
                                            data-barangay="${student.barangay_id}"
                                            data-duration="${student.duration}"
                                            data-status="${student.status}">
                                            <td class="table-col-name">
                                                ${escapeHtml(student.last_name)}, ${escapeHtml(student.first_name)}
                                                ${student.middle_name ? ' ' + escapeHtml(student.middle_name.substring(0, 1)) + '.' : ''}
                                            </td>
                                            <td class="table-col-birthday">${formatDate(student.birthday)}</td>
                                            <td class="table-col-barangay">${escapeHtml(student.barangay_name)}</td>
                                            <td class="table-col-duration">${student.duration}</td>
                                            <td class="table-col-status">
                                                <span class="status-badge ${student.status === 'Active' ? 'bg-active' : 'bg-blocked'}">
                                                    ${student.status}
                                                </span>
                                            </td>
                                            <td class="table-col-actions">
                                                <button class="btn btn-view view-student" data-id="${student.id}">
                                                    <i class="fas fa-eye"></i> <span class="action-text">View</span>
                                                </button>
                                                <button class="btn btn-edit edit-student" data-id="${student.id}">
                                                    <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                                </button>`;
                                                
                                    if (student.status === 'Active') {
                                        html += `
                                                <button class="btn btn-block block-student" data-id="${student.id}">
                                                    <i class="fas fa-ban"></i> <span class="action-text">Block</span>
                                                </button>`;
                                    } else {
                                        html += `
                                                <button class="btn btn-unblock unblock-student" data-id="${student.id}">
                                                    <i class="fas fa-check-circle"></i> <span class="action-text">Unblock</span>
                                                </button>`;
                                    }
                                                
                                    html += `</td>
                                        </tr>`;
                                });
                                
                                html += `</tbody></table></div>`;
                                
                                $('#resultsContainer').html(html);
                            } else {
                                // Show no results message
                                $('#resultsContainer').html(`
                                    <div class="no-students">
                                        <i class="fas fa-user-graduate"></i>
                                        <h4>No Students Found</h4>
                                        <p>No students match your current filters.</p>
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
                    // If no filters are active, reload the page to show normal paginated view (active only)
                    window.location.href = window.location.pathname;
                }
            }
            
            // Add debounce to prevent too many rapid requests
            $('#searchFilter, #barangayFilter, #durationFilter, #statusFilter').on('input change', function() {
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