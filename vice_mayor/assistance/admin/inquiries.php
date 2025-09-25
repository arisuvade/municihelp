
<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to safely require files with absolute paths and return connection
function get_db_connection() {
    // Get absolute path to db.php (3 levels up from current directory)
    $baseDir = dirname(__DIR__, 3);
    $dbPath = $baseDir . '/includes/db.php';
    
    if (!file_exists($dbPath)) {
        throw new Exception("Database configuration file not found: " . htmlspecialchars($dbPath));
    }
    
    require $dbPath;
    
    if (!isset($conn)) {
        throw new Exception("Database connection variable not set in db.php");
    }
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    return $conn;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn = get_db_connection();
        
        // Check if admin is logged in
        if (!isset($_SESSION['assistance_admin_id'])) {
            throw new Exception('Unauthorized access');
        }
        
        $assistance_admin_id = $_SESSION['assistance_admin_id'];
        
        switch ($_POST['action']) {
            case 'answer_inquiry':
                if (!isset($_POST['inquiry_id']) || !isset($_POST['answer'])) {
                    throw new Exception('Missing required parameters');
                }
                
                $inquiry_id = intval($_POST['inquiry_id']);
                $answer = trim($_POST['answer']);
                
                if (empty($answer)) {
                    throw new Exception('Answer cannot be empty');
                }
                
                // Update the inquiry
                $stmt = $conn->prepare("UPDATE inquiries 
                                       SET answer = ?, status = 'answered', 
                                           answeredby_admin_id = ?, updated_at = NOW() 
                                       WHERE id = ?");
                $stmt->bind_param("sii", $answer, $assistance_admin_id, $inquiry_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update inquiry: ' . $conn->error);
                }
                
                echo json_encode(['success' => true, 'message' => 'Inquiry answered successfully']);
                break;
                
            case 'view_inquiry':
                if (!isset($_POST['inquiry_id'])) {
                    throw new Exception('Inquiry ID not specified');
                }
                
                $inquiry_id = intval($_POST['inquiry_id']);
                
                // Get inquiry details with user information
                $stmt = $conn->prepare("
                    SELECT i.*, d.name as department_name, a.name as answered_by,
                           u.name as user_first_name, u.middle_name as user_middle_name, 
                           u.last_name as user_last_name, u.birthday as user_birthday,
                           u.phone as user_phone, u.address as user_address,
                           b.name as user_barangay_name
                    FROM inquiries i
                    JOIN departments d ON i.department_id = d.id
                    LEFT JOIN admins a ON i.answeredby_admin_id = a.id
                    LEFT JOIN users u ON i.user_id = u.id
                    LEFT JOIN barangays b ON u.barangay_id = b.id
                    WHERE i.id = ?
                ");
                $stmt->bind_param("i", $inquiry_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception('Inquiry not found');
                }
                
                $inquiry = $result->fetch_assoc();
                
                // Format user full name
                $user_full_name = htmlspecialchars(
    $inquiry['user_first_name'] . ' ' . 
    (!empty($inquiry['user_middle_name']) ? $inquiry['user_middle_name'] . ' ' : '') . 
    $inquiry['user_last_name']
);

                // Function to calculate age
                function calculateAge($birthday) {
                    if (empty($birthday)) return 'N/A';
                    
                    $birthDate = new DateTime($birthday);
                    $today = new DateTime();
                    $age = $today->diff($birthDate)->y;
                    return $age . ' years old';
                }

                // Calculate age
                $user_age = calculateAge($inquiry['user_birthday']);

                // Format phone number
                function formatPhoneNumber($phone) {
                    if (empty($phone)) return $phone;
                    
                    // Remove any non-digit characters first
                    $cleaned = preg_replace('/[^0-9]/', '', $phone);
                    
                    // Convert +639 (which becomes 639) to 09
                    if (substr($cleaned, 0, 3) === '639' && strlen($cleaned) === 12) {
                        return '09' . substr($cleaned, 3);
                    }
                    
                    return $phone;
                }

                $user_phone_formatted = !empty($inquiry['user_phone']) ? formatPhoneNumber($inquiry['user_phone']) : '';
                
                ob_start();
                ?>
                <div class="container-fluid px-4">
    <!-- Requester Information Section -->
    <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
        <h4 class="border-bottom pb-2 mb-3">Requester Information</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Name:</strong> <?= $user_full_name ?>
                </div>
                <?php if (!empty($inquiry['user_birthday'])): ?>
                <div class="mb-3">
                    <strong>Birthday:</strong> <?= date('F d, Y', strtotime($inquiry['user_birthday'])) ?>
                </div>
                <div class="mb-3">
                    <strong>Age:</strong> <?= $user_age ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if (!empty($user_phone_formatted)): ?>
                <div class="mb-3">
                    <strong>Phone:</strong> <?= htmlspecialchars($user_phone_formatted) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($inquiry['user_barangay_name'])): ?>
                <div class="mb-3">
                    <strong>Barangay:</strong> <?= htmlspecialchars($inquiry['user_barangay_name']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($inquiry['user_address'])): ?>
                <div class="mb-3">
                    <strong>Address:</strong> <?= htmlspecialchars($inquiry['user_address']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

                        <!-- Inquiry Information Section -->
    <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
        <h4 class="border-bottom pb-2 mb-3">Inquiry Information</h4>
        <div class="row">
            <!-- LEFT SIDE -->
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Status:</strong> <?= ucfirst($inquiry['status']) ?>
                </div>
                <div class="mb-3">
                    <strong>Department:</strong> <?= htmlspecialchars($inquiry['department_name']) ?>
                </div>
                <div class="mb-3">
                    <strong>Submitted:</strong> <?= date('F d, Y h:i A', strtotime($inquiry['created_at'])) ?>
                </div>
            </div>
                            
                            <!-- RIGHT SIDE -->
<div class="col-md-6">
    <?php if ($inquiry['status'] === 'answered' && !empty($inquiry['updated_at'])): ?>
        <?php if (!empty($inquiry['answered_by'])): ?>
        <div class="mb-3">
            <strong>Answered By:</strong> <?= htmlspecialchars($inquiry['answered_by']) ?>
        </div>
        <?php endif; ?>
        <div class="mb-3">
            <strong>Answered On:</strong> <?= date('F d, Y h:i A', strtotime($inquiry['updated_at'])) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($inquiry['status'] === 'cancelled'): ?>
        <div class="mb-3"><strong>Cancelled by:</strong> User</div>
        <div class="mb-3"><strong>Cancelled Date:</strong> <?= date('F d, Y h:i A', strtotime($inquiry['updated_at'])) ?></div>
    <?php endif; ?>
</div>
        </div>
    </div>

                    <!-- Question and Answer Section -->
                    <div class="row">
                        <div class="col-12 mb-4">
                            <div class="document-card h-100 bg-white rounded shadow-sm overflow-hidden">
                                <div class="document-header bg-light p-3 border-bottom">
                                    <h6 class="mb-0">Question</h6>
                                </div>
                                <div class="document-body p-3">
                                    <p><?= nl2br(htmlspecialchars($inquiry['question'])) ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if ($inquiry['status'] === 'answered' && !empty($inquiry['answer'])): ?>
                        <div class="col-12 mb-4">
                            <div class="document-card h-100 bg-white rounded shadow-sm overflow-hidden">
                                <div class="document-header bg-light p-3 border-bottom">
                                    <h6 class="mb-0">Admin's Answer</h6>
                                </div>
                                <div class="document-body p-3">
                                    <p><?= nl2br(htmlspecialchars($inquiry['answer'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                echo ob_get_clean();
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        $conn->close();
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Main page functionality
try {
    // Get database connection first
    $conn = get_db_connection();

    // Check if admin is logged in
    if (!isset($_SESSION['assistance_admin_id'])) {
        header("Location: ../../../includes/auth/login.php");
        exit();
    }

    // Get admin info and department ID
    $assistance_admin_id = $_SESSION['assistance_admin_id'];
    $admin_query = $conn->query("SELECT name FROM admins WHERE id = $assistance_admin_id");
    if (!$admin_query) {
        throw new Exception('Failed to fetch admin info: ' . $conn->error);
    }
    $admin_data = $admin_query->fetch_assoc();
    $admin_name = $admin_data['name'] ?? 'Admin';

    // Get the assistance department ID
    $dept_query = $conn->query("SELECT id FROM departments WHERE name = 'Assistance'");
    if (!$dept_query) {
        throw new Exception('Failed to fetch department info: ' . $conn->error);
    }
    $dept_data = $dept_query->fetch_assoc();
    $department_id = $dept_data['id'] ?? null;

    if (!$department_id) {
        throw new Exception("Could not determine department ID");
    }

    $pageTitle = 'Inquiries';
    include '../../../includes/header.php';

    function formatPhoneNumber($phone) {
        if (strpos($phone, '+63') === 0) {
            return '0' . substr($phone, 3);
        }
        return $phone;
    }

    // Get inquiries for this department only, with pending first
    $inquiries_query = $conn->query("
        SELECT i.id, i.question, i.status, i.created_at, 
               u.phone, d.name as department_name,
               u.name, u.middle_name, u.last_name
        FROM inquiries i
        JOIN users u ON i.user_id = u.id
        JOIN departments d ON i.department_id = d.id
        WHERE i.department_id = $department_id
        ORDER BY 
            CASE WHEN i.status = 'pending' THEN 0 ELSE 1 END,
            i.created_at ASC
    ");
    
    if (!$inquiries_query) {
        throw new Exception('Failed to fetch inquiries: ' . $conn->error);
    }
    
    $inquiries = $inquiries_query->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die('<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
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
            --completed: #4361ee;
            --answered: #4361ee;
            --approved: #28A745;
            --declined: #DC3545;
            --cancelled: #6C757D;
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

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .status-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }

        .status-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.2rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }

        .table-responsive {
            border-radius: 0 0 10px 10px;
            overflow-x: auto;
            width: 100%;
        }

        .table {
            width: 100%;
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

        .btn-view {
            background-color: var(--completed);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-view:hover {
            background-color: #3a56d4;
            color: white;
        }
        
        .btn-answer {
            background-color: var(--approved);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-answer:hover {
            background-color: #218838;
            color: white;
        }

        .no-inquiries {
            text-align: center;
            padding: 3rem;
            color: #666;
            margin-top: 2rem;
        }

        .no-inquiries i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }

        .no-inquiries h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .no-inquiries p {
            font-size: 1rem;
            color: #6c757d;
        }

        .status-badge {
            padding: 0.5em 0.8em;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 50px;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }

        .bg-pending {
            background-color: var(--pending) !important;
            color: #212529;
        }

        .bg-answered {
            background-color: var(--answered) !important;
            color: white;
        }

        .bg-cancelled {
            background-color: var(--cancelled) !important;
            color: white;
        }

        .table-col-seq {
            width: 5%;
            text-align: center;
        }
        
        .table-col-name {
            width: 22%;
        }
        
        .table-col-phone {
            width: 10%;
            text-align: center;
        }

        .table-col-question {
            width: 25%;
        }

        .table-col-date {
            width: 15%;
            text-align: center;
        }

        .table-col-status {
            width: 10%;
            text-align: center;
        }

        .table-col-actions {
            width: 15%;
            text-align: center;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .mobile-row {
                display: flex;
                flex-direction: column;
                padding: 1rem;
                border-bottom: 1px solid #dee2e6;
                position: relative;
                padding-bottom: 3rem;
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
            
            .mobile-row .action-buttons {
                position: absolute;
                bottom: 0.5rem;
                right: 0.5rem;
                display: flex;
                gap: 8px;
            }
            
            .btn-view, .btn-answer {
                padding: 0.4rem;
                font-size: 0;
                width: 35px;
                height: 35px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .btn-view i, 
            .btn-answer i {
                font-size: 0.9rem;
                margin: 0;
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

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }

        .document-card {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
        
        .document-header {
            background-color: #f8f9fa;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .document-body {
            padding: 1.25rem;
        }
        
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Inquiries</h1>
                    <p class="mb-0">View and respond to citizen inquiries</p>
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
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Search Contact</label>
                        <input type="text" class="form-control" id="searchFilter" 
                            placeholder="Search by contact number">
                    </div>
                    
                    <div class="filter-group">
                        <label for="statusFilter" class="form-label">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="pending" selected>Pending</option>
                            <option value="answered">Answered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="dateFilter" class="form-label">Date Submitted</label>
                        <input type="date" class="form-control" id="dateFilter">
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div id="resultsContainer">
            <?php if (empty($inquiries)): ?>
                <div class="no-inquiries">
                    <i class="fas fa-inbox"></i>
                    <h4>No Inquiries Found</h4>
                    <p>There are no inquiries to display.</p>
                </div>
            <?php else: ?>
                <!-- Desktop Table -->
                <div class="status-card card mb-4 desktop-table">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th class="table-col-seq">#</th>
                                        <th class="table-col-name">Name</th>
                                        <th class="table-col-phone">Contact No.</th>
                                        <th class="table-col-question">Question</th>
                                        <th class="table-col-date">Date Submitted</th>
                                        <th class="table-col-status">Status</th>
                                        <th class="table-col-actions"></th>
                                    </tr>
                                </thead>
                                <tbody id="inquiriesTableBody">
                                    <?php 
                                    $sequence_number = 1;
                                    foreach ($inquiries as $inquiry): 
                                        $phone_number = formatPhoneNumber($inquiry['phone']);
                                        $short_question = strlen($inquiry['question']) > 20 ? 
                                            substr($inquiry['question'], 0, 20) . '...' : $inquiry['question'];
                                        $full_name = $inquiry['last_name'] . ', ' . $inquiry['name'] . 
                                            (!empty($inquiry['middle_name']) ? ' ' . substr($inquiry['middle_name'], 0, 1) . '.' : '');
                                    ?>
                                        <tr class="inquiry-row" 
                                            data-id="<?= $inquiry['id'] ?>"
                                            data-phone="<?= htmlspecialchars($phone_number) ?>"
                                            data-status="<?= htmlspecialchars($inquiry['status']) ?>"
                                            data-date="<?= date('Y-m-d', strtotime($inquiry['created_at'])) ?>">
                                            <td class="table-col-seq"><?= $sequence_number ?></td>
                                            <td class="table-col-name"><?= htmlspecialchars($full_name) ?></td>
                                            <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                            <td class="table-col-question"><?= htmlspecialchars($short_question) ?></td>
                                            <td class="table-col-date"><?= date('F j, Y', strtotime($inquiry['created_at'])) ?></td>
                                            <td class="table-col-status">
                                                <span class="status-badge <?= $inquiry['status'] === 'answered' ? 'bg-answered' : 
                                                    ($inquiry['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending') ?>">
                                                    <?= ucfirst($inquiry['status']) ?>
                                                </span>
                                            </td>
                                            <td class="table-col-actions">
                                                <div class="action-buttons">
                                                    <button class="btn btn-view view-btn" data-id="<?= $inquiry['id'] ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if ($inquiry['status'] === 'pending'): ?>
                                                        <button class="btn btn-answer answer-btn" data-id="<?= $inquiry['id'] ?>">
                                                            <i class="fas fa-reply"></i> Answer
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                    $sequence_number++;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Table -->
                <div class="mobile-table" id="mobileInquiriesTable">
                    <?php 
                    $sequence_number = 1;
                    foreach ($inquiries as $inquiry): 
                        $phone_number = formatPhoneNumber($inquiry['phone']);
                        $short_question = strlen($inquiry['question']) > 20 ? 
                            substr($inquiry['question'], 0, 20) . '...' : $inquiry['question'];
                        $full_name = $inquiry['last_name'] . ', ' . $inquiry['first_name'] . 
                            (!empty($inquiry['middle_name']) ? ' ' . substr($inquiry['middle_name'], 0, 1) . '.' : '');
                    ?>
                        <div class="mobile-row inquiry-row" 
                            data-id="<?= $inquiry['id'] ?>"
                            data-phone="<?= htmlspecialchars($phone_number) ?>"
                            data-status="<?= htmlspecialchars($inquiry['status']) ?>"
                            data-date="<?= date('Y-m-d', strtotime($inquiry['created_at'])) ?>">
                            <div>
                                <span class="mobile-label">#:</span>
                                <?= $sequence_number ?>
                            </div>
                            <div>
                                <span class="mobile-label">Name:</span>
                                <?= htmlspecialchars($full_name) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Contact:</span>
                                <?= htmlspecialchars($phone_number) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Question:</span>
                                <?= htmlspecialchars($short_question) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Date:</span>
                                <?= date('F j, Y', strtotime($inquiry['created_at'])) ?>
                            </div>
                            <div>
                                <span class="mobile-label">Status:</span>
                                <span class="status-badge <?= $inquiry['status'] === 'answered' ? 'bg-answered' : 
                                    ($inquiry['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending') ?>">
                                    <?= ucfirst($inquiry['status']) ?>
                                </span>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-view view-btn" data-id="<?= $inquiry['id'] ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($inquiry['status'] === 'pending'): ?>
                                    <button class="btn btn-answer answer-btn" data-id="<?= $inquiry['id'] ?>">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                    $sequence_number++;
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- View Modal -->
        <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Inquiry Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="viewModalContent">
                        <!-- Content loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Answer Modal -->
        <div class="modal fade" id="answerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Answer Inquiry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="answerValidation" class="alert alert-danger d-none mb-3"></div>
                        <div class="mb-3">
                            <label for="answerText" class="form-label">Question:</label>
                            <div id="questionContent" class="p-3 bg-light rounded"></div>
                        </div>
                        <div class="mb-3">
                            <label for="answerText" class="form-label">Your Answer:</label>
                            <textarea class="form-control" id="answerText" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="confirmAnswer">Submit Answer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
        <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Success</h5>
                    </div>
                    <div class="modal-body text-center">
                        <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                        <h4 id="successMessage">Operation Completed Successfully!</h4>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="continueAfterSuccess">Continue</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        let currentInquiryId = null;
        
        // View Inquiry
        $(document).on('click', '.view-btn', function() {
            const inquiryId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'view_inquiry',
                    inquiry_id: inquiryId
                },
                success: function(data) {
                    $('#viewModalContent').html(data);
                },
                error: function(xhr, status, error) {
                    $('#viewModalContent').html('<div class="alert alert-danger">Failed to load inquiry details: ' + error + '</div>');
                }
            });
        });

        // Answer Inquiry
        $(document).on('click', '.answer-btn', function() {
            currentInquiryId = $(this).data('id');
            $('#answerValidation').addClass('d-none').text('');
            $('#answerText').val('');
            
            // Load the question content
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'view_inquiry',
                    inquiry_id: currentInquiryId
                },
                success: function(data) {
                    // Create a temporary div to parse the response
                    const $temp = $('<div>').html(data);
                    // Find the question in the document-body of the first document-card
                    const questionContent = $temp.find('.document-card .document-body p').first().html();
                    $('#questionContent').html(questionContent || '<p>Failed to load question.</p>');
                    $('#answerModal').modal('show');
                },
                error: function(xhr, status, error) {
                    $('#questionContent').html('<p>Failed to load question: ' + error + '</p>');
                    $('#answerModal').modal('show');
                }
            });
        });

   // Submit Answer - Updated to use update_status.php
$('#confirmAnswer').click(function() {
    const answerText = $('#answerText').val().trim();
    const $validation = $('#answerValidation');
    const $btn = $(this);
    
    if (!answerText) {
        $validation.removeClass('d-none').text('Please provide an answer.');
        return;
    }
    
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
    
    $.ajax({
        url: 'update_status.php',
        type: 'POST',
        data: {
            action: 'answer_inquiry',
            inquiry_id: currentInquiryId,
            answer: answerText
        },
        dataType: 'json', // Explicitly expect JSON response
        success: function(data) {
            if (data.success) {
                $('#answerModal').modal('hide');
                $('#successMessage').text(data.message || 'Inquiry answered successfully!');
                const successModal = new bootstrap.Modal('#successModal');
                successModal.show();
                
                $('#continueAfterSuccess').off().click(function() {
                    successModal.hide();
                    location.reload();
                });
            } else {
                $validation.removeClass('d-none').text(data.message || 'Failed to submit answer.');
            }
        },
        error: function(xhr, status, error) {
            // Improved error handling
            let errorMsg = 'Error: ';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg += xhr.responseJSON.error;
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg += response.error || xhr.responseText;
                } catch (e) {
                    errorMsg += xhr.responseText;
                }
            } else {
                errorMsg += error;
            }
            $validation.removeClass('d-none').text(errorMsg);
        },
        complete: function() {
            $btn.prop('disabled', false).text('Submit Answer');
        }
    });
});

        // Filter functionality
        function applyFilters() {
            const searchTerm = $('#searchFilter').val().toLowerCase();
            const statusFilter = $('#statusFilter').val();
            const dateFilter = $('#dateFilter').val();
            
            let anyVisible = false;
            
            $('.no-inquiries-filtered').remove();
            const hasInquiries = $('.inquiry-row').length > 0;
            
            $('.inquiry-row').each(function() {
                const $row = $(this);
                const phone = $row.data('phone');
                const status = $row.data('status');
                const date = $row.data('date');
                
                const matchesSearch = !searchTerm || phone.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesDate = !dateFilter || date === dateFilter;
                
                if (matchesSearch && matchesStatus && matchesDate) {
                    $row.show();
                    anyVisible = true;
                } else {
                    $row.hide();
                }
            });
            
            if (!anyVisible) {
                if (!hasInquiries) {
                    $('.no-inquiries').show();
                    $('.desktop-table, .mobile-table').hide();
                } else {
                    $('#resultsContainer').append(`
                        <div class="no-inquiries no-inquiries-filtered">
                            <i class="fas fa-inbox"></i>
                            <h4>No Inquiries Found</h4>
                            <p>No inquiries match your current filters.</p>
                        </div>
                    `);
                    $('.desktop-table, .mobile-table').hide();
                }
            } else {
                $('.no-inquiries-filtered').remove();
                if (window.innerWidth > 768) {
                    $('.desktop-table').show();
                    $('.mobile-table').hide();
                } else {
                    $('.desktop-table').hide();
                    $('.mobile-table').show();
                }
                $('.no-inquiries').hide();
            }
        }
        
        $('#searchFilter').on('input', applyFilters);
        $('#statusFilter, #dateFilter').on('change', applyFilters);
        applyFilters();
    });
    </script>
        <?php include '../../../includes/footer.php'; ?>
</body>
</html>