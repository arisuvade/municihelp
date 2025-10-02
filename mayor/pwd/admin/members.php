<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['pwd_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$pwd_admin_id = $_SESSION['pwd_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $pwd_admin_id");
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
        
        $query = "SELECT m.id, m.first_name, m.middle_name, m.last_name, m.birthday, 
                         m.barangay_id, br.name as barangay_name
                  FROM pwd_birthday_members m
                  JOIN barangays br ON m.barangay_id = br.id
                  WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $query .= " AND (m.first_name LIKE CONCAT('%', ?, '%') 
                          OR m.last_name LIKE CONCAT('%', ?, '%') 
                          OR m.id = ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = is_numeric($search) ? $search : 0;
            $types .= 'ssi';
        }
        
        if ($barangay > 0) {
            $query .= " AND m.barangay_id = ?";
            $params[] = $barangay;
            $types .= 'i';
        }
        
        $query .= " ORDER BY 
            CASE 
                WHEN DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') THEN 1
                WHEN DATE_FORMAT(birthday, '%m-%d') > DATE_FORMAT(CURDATE(), '%m-%d') THEN 2
                ELSE 3
            END,
            DATE_FORMAT(birthday, '%m-%d'),
            m.last_name, m.first_name";
        
        $stmt = $conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode($members);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

$pageTitle = 'PWD Birthday Members';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
        $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
        $barangay_id = intval($_POST['barangay_id'] ?? 0);
        
        $stmt = $conn->prepare("INSERT INTO pwd_birthday_members 
                                (first_name, middle_name, last_name, birthday, barangay_id) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $first_name, $middle_name, $last_name, $birthday, $barangay_id);
        
        if ($stmt->execute()) {
            header("Location: members.php?created=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error adding member: ' . $conn->error;
            header("Location: members.php");
            exit();
        }
    }
    elseif (isset($_POST['update_member'])) {
        $id = intval($_POST['id']);
        $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
        $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
        $barangay_id = intval($_POST['barangay_id'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE pwd_birthday_members 
                               SET first_name = ?, middle_name = ?, last_name = ?, 
                                   birthday = ?, barangay_id = ?
                               WHERE id = ?");
        $stmt->bind_param("ssssii", $first_name, $middle_name, $last_name, $birthday, $barangay_id, $id);
        
        if ($stmt->execute()) {
            header("Location: members.php?updated=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error updating member: ' . $conn->error;
            header("Location: members.php");
            exit();
        }
    }
    elseif (isset($_POST['delete_member'])) {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM pwd_birthday_members WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: members.php?deleted=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error deleting member: ' . $conn->error;
            header("Location: members.php");
            exit();
        }
    }
}

// Get current count of members
$count_result = $conn->query("SELECT COUNT(*) as total FROM pwd_birthday_members");
$count_row = $count_result->fetch_assoc();
$current_count = $count_row['total'];

// Get today's birthdays
$today = date('m-d');
$birthday_query = $conn->query("
    SELECT m.id, m.first_name, m.middle_name, m.last_name, m.birthday, 
           br.name as barangay_name, 
           CASE 
               WHEN DATE_FORMAT(m.birthday, '%m-%d') = '$today' THEN 'today'
               WHEN DATE_FORMAT(m.birthday, '%m-%d') BETWEEN 
                    DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '%m-%d') AND 
                    DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d') THEN 'upcoming'
               ELSE 'other'
           END as birthday_status
    FROM pwd_birthday_members m
    JOIN barangays br ON m.barangay_id = br.id
    WHERE DATE_FORMAT(m.birthday, '%m-%d') BETWEEN 
          DATE_FORMAT(CURDATE(), '%m-%d') AND 
          DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d')
    ORDER BY 
        CASE 
            WHEN DATE_FORMAT(m.birthday, '%m-%d') = '$today' THEN 1
            ELSE 2
        END,
        DATE_FORMAT(m.birthday, '%m-%d'),
        m.last_name, m.first_name
");
$birthday_members = $birthday_query ? $birthday_query->fetch_all(MYSQLI_ASSOC) : [];

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total count
$total_query = "SELECT COUNT(*) as total FROM pwd_birthday_members";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_members = $total_row['total'];
$total_pages = ceil($total_members / $per_page);

// Get members for current page - sorted by birthday (today first, then upcoming, then others)
$query = "SELECT m.id, m.first_name, m.middle_name, m.last_name, m.birthday, 
                 m.barangay_id, br.name as barangay_name
          FROM pwd_birthday_members m
          JOIN barangays br ON m.barangay_id = br.id
          ORDER BY 
            CASE 
                WHEN DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') THEN 1
                WHEN DATE_FORMAT(birthday, '%m-%d') > DATE_FORMAT(CURDATE(), '%m-%d') THEN 2
                ELSE 3
            END,
            DATE_FORMAT(birthday, '%m-%d'),
            m.last_name, m.first_name
          LIMIT $per_page OFFSET $offset";
$result = $conn->query($query);
$members = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
            --birthday: #E91E63;
            --upcoming: #FF9800;
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
        
        .no-members {
            text-align: center;
            padding: 3rem;
            color: #666;
            margin-top: 2rem;
        }
        
        .no-members i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        .no-members h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-members p {
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
        
        .birthday-section {
            background-color: #fff8e1;
            border-left: 4px solid var(--birthday);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .birthday-title {
            color: var(--birthday);
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .birthday-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .birthday-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .birthday-item {
            background-color: white;
            padding: 10px 15px;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            border: 1px solid #ffcdd2;
        }
        
        .birthday-item.today {
            background-color: #ffebee;
            border: 1px solid var(--birthday);
        }
        
        .birthday-item.upcoming {
            background-color: #fff3e0;
            border: 1px solid var(--upcoming);
        }
        
        .birthday-icon {
            color: var(--birthday);
            margin-right: 8px;
        }
        
        .upcoming .birthday-icon {
            color: var(--upcoming);
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
            
            .birthday-list {
                flex-direction: column;
            }
            
            .birthday-item {
                width: 100%;
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
                    <h1 class="mb-1">PWD Birthday Members</h1>
                    <p class="mb-0">Manage all PWD members and their birthdays</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Birthday Section -->
        <?php if (!empty($birthday_members)): ?>
            <div class="birthday-section">
                <div class="birthday-title">
                    <i class="fas fa-birthday-cake"></i>
                    <?php 
                    $today_count = 0;
                    $upcoming_count = 0;
                    foreach ($birthday_members as $member) {
                        if ($member['birthday_status'] == 'today') $today_count++;
                        else $upcoming_count++;
                    }
                    
                    if ($today_count > 0) {
                        echo "Today's Birthdays ($today_count)";
                    } else {
                        echo "Upcoming Birthdays ($upcoming_count)";
                    }
                    ?>
                </div>
                <div class="birthday-list">
                    <?php foreach ($birthday_members as $member): ?>
                        <div class="birthday-item <?= $member['birthday_status'] ?>">
                            <i class="fas fa-birthday-cake birthday-icon"></i>
                            <strong><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></strong>
                            <span class="mx-2">-</span>
                            <?= date('F j', strtotime($member['birthday'])) ?>
                            <span class="mx-2">-</span>
                            <?= htmlspecialchars($member['barangay_name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Header -->
        <div class="action-header">
            <h2 class="mb-0">Members List</h2>
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                <i class="fas fa-plus"></i> <span class="action-text">Add Member</span>
            </button>
        </div>

        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Search by Name</label>
                        <input type="text" class="form-control" id="searchFilter" placeholder="Search members">
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

        <!-- Error Messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Members List -->
        <div id="resultsContainer">
            <?php if (empty($members)): ?>
                 <!-- Only show this message when there are no members at all -->
                <div class="no-members">
                    <i class="fas fa-users"></i>
                    <h4>No Members Found</h4>
                    <p>There are no members to display.</p>
                </div>
            <?php else: 
         // Calculate starting sequence number for this page
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
                                <th class="table-col-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): 
                                $is_today = date('m-d') == date('m-d', strtotime($member['birthday']));
                                $is_upcoming = false;
                                $week_later = date('Y-m-d', strtotime('+7 days'));
                                $birthday_this_year = date('Y') . date('-m-d', strtotime($member['birthday']));
                                if ($birthday_this_year >= date('Y-m-d') && $birthday_this_year <= $week_later) {
                                    $is_upcoming = true;
                                }
                            ?>
                                <tr class="member-row" 
                                    data-id="<?= $member['id'] ?>"
                                    data-firstname="<?= htmlspecialchars(strtolower($member['first_name'])) ?>"
                                    data-lastname="<?= htmlspecialchars(strtolower($member['last_name'])) ?>"
                                    data-barangay="<?= $member['barangay_id'] ?>">
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                    <td class="table-col-name">
                                        <?php if ($is_today): ?>
                                            <span class="badge bg-danger me-1">Today!</span>
                                        <?php elseif ($is_upcoming): ?>
                                            <span class="badge bg-warning me-1">Soon</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($member['last_name']) ?>, <?= htmlspecialchars($member['first_name']) ?>
                                        <?= !empty($member['middle_name']) ? ' ' . htmlspecialchars($member['middle_name'], 0, 1): '' ?>
                                    </td>
                                    <td class="table-col-birthday">
                                        <?= date('F j, Y', strtotime($member['birthday'])) ?>
                                    </td>
                                    <td class="table-col-barangay"><?= htmlspecialchars($member['barangay_name']) ?></td>
                                    <td class="table-col-actions">
                                        <button class="btn btn-edit edit-member" data-id="<?= $member['id'] ?>">
                                            <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                        </button>
                                        <button class="btn btn-delete delete-member" data-id="<?= $member['id'] ?>">
                                            <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                        $sequence_number++; endforeach; ?>
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

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addMemberModalLabel">Add PWD Member</h5>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_member">Add Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="edit_member_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMemberModalLabel">Edit PWD Member</h5>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_member">Update Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-labelledby="deleteMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="delete_member_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteMemberModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! Are you absolutely sure you want to delete this member?
                        </div>
                        <div class="mb-3">
                            <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                            <input type="text" class="form-control" id="confirmDelete" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_member" id="submitDelete" disabled>Delete Member</button>
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
                    <h5 class="modal-title">Member Added</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Member Added Successfully!</h4>
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
                    <h5 class="modal-title">Member Updated</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Member Updated Successfully!</h4>
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
                    <h5 class="modal-title">Member Deleted</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Member Deleted Successfully!</h4>
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

            // Edit Member
            $(document).on('click', '.edit-member', function() {
                const memberId = $(this).data('id');
                
                // Find the member in our current data
                const member = <?= json_encode($members) ?>.find(m => m.id == memberId);
                
                if (member) {
                    $('#edit_member_id').val(member.id);
                    $('#edit_first_name').val(member.first_name);
                    $('#edit_middle_name').val(member.middle_name || '');
                    $('#edit_last_name').val(member.last_name);
                    $('#edit_birthday').val(member.birthday);
                    $('#edit_barangay_id').val(member.barangay_id);
                    
                    $('#editMemberModal').modal('show');
                }
            });

            // Delete Member
            $(document).on('click', '.delete-member', function() {
                const memberId = $(this).data('id');
                $('#delete_member_id').val(memberId);
                $('#confirmDelete').val(''); // Clear previous input
                $('#submitDelete').prop('disabled', true);
                $('#deleteMemberModal').modal('show');
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

            // Live Filtering with AJAX
            let searchTimer;
            
            function applyFilters() {
                const searchTerm = $('#searchFilter').val().trim();
                const barangayFilter = $('#barangayFilter').val();
                
                // Only search if there's an active filter
                if (searchTerm || barangayFilter) {
                    // Show loading indicator
                    $('#resultsContainer').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Searching...</p></div>');
                    
                    // Make AJAX request to search all members
                    $.ajax({
                        url: window.location.href,
                        data: {
                            ajax_search: 1,
                            search: searchTerm,
                            barangay: barangayFilter
                        },
                        dataType: 'json',
                        success: function(members) {
                            if (members.error) {
                                $('#resultsContainer').html(`
                                    <div class="alert alert-danger">
                                        ${members.error}
                                    </div>
                                `);
                                return;
                            }
                            
                            if (members.length > 0) {
                                // Build the results table
                                let html = `
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="table-col-name">Name</th>
                                                    <th class="table-col-birthday">Birthday</th>
                                                    <th class="table-col-barangay">Barangay</th>
                                                    <th class="table-col-actions">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                                
                                members.forEach(member => {
                                    const today = new Date();
                                    const birthday = new Date(member.birthday);
                                    const is_today = today.getMonth() === birthday.getMonth() && 
                                                    today.getDate() === birthday.getDate();
                                    
                                    const weekLater = new Date();
                                    weekLater.setDate(today.getDate() + 7);
                                    const birthdayThisYear = new Date(member.birthday);
                                    birthdayThisYear.setFullYear(today.getFullYear());
                                    
                                    const is_upcoming = birthdayThisYear >= today && birthdayThisYear <= weekLater;
                                    
                                    html += `
                                        <tr class="member-row" 
                                            data-id="${member.id}"
                                            data-firstname="${escapeHtml(member.first_name).toLowerCase()}"
                                            data-lastname="${escapeHtml(member.last_name).toLowerCase()}"
                                            data-barangay="${member.barangay_id}">
                                            <td class="table-col-name">
                                                ${is_today ? '<span class="badge bg-danger me-1">Today!</span>' : ''}
                                                ${!is_today && is_upcoming ? '<span class="badge bg-warning me-1">Soon</span>' : ''}
                                                ${escapeHtml(member.last_name)}, ${escapeHtml(member.first_name)}
                                                ${member.middle_name ? ' ' + escapeHtml(member.middle_name.substring(0, 1)) + '.' : ''}
                                            </td>
                                            <td class="table-col-birthday">${formatDate(member.birthday)}</td>
                                            <td class="table-col-barangay">${escapeHtml(member.barangay_name)}</td>
                                            <td class="table-col-actions">
                                                <button class="btn btn-edit edit-member" data-id="${member.id}">
                                                    <i class="fas fa-edit"></i> <span class="action-text">Edit</span>
                                                </button>
                                                <button class="btn btn-delete delete-member" data-id="${member.id}">
                                                    <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
                                                </button>
                                            </td>
                                        </tr>`;
                                });
                                
                                html += `</tbody></table></div>`;
                                
                                $('#resultsContainer').html(html);
                            } else {
                                // Show no results message
                                $('#resultsContainer').html(`
                                    <div class="no-members">
                                        <i class="fas fa-users"></i>
                                        <h4>No Members Found</h4>
                                        <p>No members match your current filters.</p>
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
            
            // Format date for display
            function formatDate(dateString) {
                const date = new Date(dateString);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                return date.toLocaleDateString('en-US', options);
            }
            
            // Add debounce to prevent too many rapid requests
            $('#searchFilter, #barangayFilter').on('input change', function() {
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