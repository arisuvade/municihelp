<?php
session_start();
require_once '../../includes/db.php';
// Include the file deletion function
require_once '../../includes/delete_request_files.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $requestId = (int)$_POST['request_id'];
    $requestType = $_POST['request_type'];
    $userId = (int)$_SESSION['user_id'];
    
    // Initialize success/error messages
    $successMessage = '';
    $errorMessage = 'Unable to cancel request';
    
    switch ($requestType) {
        case 'claim':
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Get the dog_id first
                $stmt = $conn->prepare("SELECT dog_id FROM dog_claims WHERE id = ? AND user_id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $requestId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $claim = $result->fetch_assoc();
                
                if ($claim) {
                    // Update claim status
                    $stmt = $conn->prepare("UPDATE dog_claims SET status = 'cancelled' WHERE id = ?");
                    $stmt->bind_param("i", $requestId);
                    $stmt->execute();
                    
                    // Update dog status back to for_claiming
                    $stmt = $conn->prepare("UPDATE dogs SET status = 'for_claiming' WHERE id = ?");
                    $stmt->bind_param("i", $claim['dog_id']);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = 'Claim request cancelled successfully';
                } else {
                    $errorMessage = 'Claim not found or already processed';
                    throw new Exception($errorMessage);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error_message'] = $errorMessage;
            }
            break;
            
        case 'adoption':
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Get the dog_id first
                $stmt = $conn->prepare("SELECT dog_id FROM dog_adoptions WHERE id = ? AND user_id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $requestId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $adoption = $result->fetch_assoc();
                
                if ($adoption) {
                    // Update adoption status
                    $stmt = $conn->prepare("UPDATE dog_adoptions SET status = 'cancelled' WHERE id = ?");
                    $stmt->bind_param("i", $requestId);
                    $stmt->execute();
                    
                    // Update dog status back to for_adoption
                    $stmt = $conn->prepare("UPDATE dogs SET status = 'for_adoption' WHERE id = ?");
                    $stmt->bind_param("i", $adoption['dog_id']);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = 'Adoption request cancelled successfully';
                } else {
                    $errorMessage = 'Adoption not found or already processed';
                    throw new Exception($errorMessage);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error_message'] = $errorMessage;
            }
            break;
            
        case 'report':
    // Start transaction for rabid report cancellation
    $conn->begin_transaction();
    
    try {
        // Get the proof_path first before cancellation
        $stmt = $conn->prepare("SELECT proof_path FROM rabid_reports WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $requestId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        
        if ($report) {
            // Delete the uploaded proof file FIRST
            if (!empty($report['proof_path'])) {
                $filesDeleted = deleteRequestFiles('rabid_reports', $requestId, $conn);
                
                // Clear file reference from database
                $referencesCleared = clearFileReferences('rabid_reports', $requestId, $conn);
                
                if (!$filesDeleted || !$referencesCleared) {
                    error_log("Warning: File deletion may have failed for rabid report ID: $requestId");
                }
            }
            
            // THEN update report status
            $stmt = $conn->prepare("UPDATE rabid_reports SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success_message'] = 'Report cancelled successfully';
        } else {
            $errorMessage = 'Report not found or already processed';
            throw new Exception($errorMessage);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $errorMessage;
    }
    break;
            
        default:
            $_SESSION['error_message'] = 'Invalid request type';
            break;
    }
    
    header("Location: status.php");
    exit;
}

// Get all claims
$claims = $conn->query("
    SELECT c.id, d.breed, c.name_of_dog, 
           CONCAT(c.last_name, ', ', c.first_name, ' ', IFNULL(c.middle_name, '')) as full_name,
           c.created_at, c.status, c.complete_address
    FROM dog_claims c
    JOIN dogs d ON c.dog_id = d.id
    WHERE c.user_id = {$_SESSION['user_id']}
    ORDER BY c.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Get all adoptions
$adoptions = $conn->query("
    SELECT a.id, d.breed, 
           CONCAT(a.last_name, ', ', a.first_name, ' ', IFNULL(a.middle_name, '')) as full_name,
           a.created_at, a.status, a.reason
    FROM dog_adoptions a
    JOIN dogs d ON a.dog_id = d.id
    WHERE a.user_id = {$_SESSION['user_id']}
    ORDER BY a.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Get all rabid reports
$reports = $conn->query("
    SELECT id, location, 
           CONCAT(last_name, ', ', first_name, ' ', IFNULL(middle_name, '')) as full_name,
           created_at, status, description
    FROM rabid_reports
    WHERE user_id = {$_SESSION['user_id']}
    ORDER BY id DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Animal Control - Status";
include '../../includes/header.php';

// Check if there are any requests at all
$hasAnyRequests = !empty($claims) || !empty($adoptions) || !empty($reports);
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
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --approved: #28A745;
            --completed: #4361ee;
            --declined: #DC3545;
            --cancelled: #6C757D;
            --verified: #4361ee;
            --false-report: #DC3545;
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
            font-size: 1.5rem;
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
        
        .bg-approved {
            background-color: var(--approved) !important;
            color: white;
        }
        
        .bg-declined {
            background-color: var(--declined) !important;
            color: white;
        }
        
        .bg-completed {
            background-color: var(--completed) !important;
            color: white;
        }
        
        .bg-cancelled {
            background-color: var(--cancelled) !important;
            color: white;
        }
        
        .bg-verified {
            background-color: var(--verified) !important;
            color: white;
        }
        
        .bg-false-report {
            background-color: var(--false-report) !important;
            color: white;
        }
        
        .btn-view {
            background-color: var(--completed);
            color: white;
            border: none;
            padding: 0.5rem 0.9rem;
            border-radius: 5px;
            transition: all 0.3s;
            min-width: 80px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-view:hover {
            background-color: #0E3B85;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-cancel {
            background-color: var(--declined);
            color: white;
            border: none;
            padding: 0.5rem 0.9rem;
            border-radius: 5px;
            transition: all 0.3s;
            min-width: 80px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-cancel:hover {
            background-color: #bb2d3b;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }
        
        .no-requests-message {
            text-align: center;
            padding: 5rem 0;
            color: #666;
        }
        
        .no-requests-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        /* Column widths for desktop */
        .table-col-id {
            width: 6%;
            text-align: center;
        }
        
        .table-col-name {
            width: 47%;
        }
        
        .table-col-status {
            width: 22%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 20%;
            text-align: center;
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
            
            .status-badge {
                font-size: 0.8rem;
                padding: 0.4em 0.7em;
                min-width: 80px;
            }
            
            .action-buttons {
                justify-content: flex-end;
            }
            
            .btn-view, .btn-cancel {
                padding: 0.6rem;
                font-size: 0;
                width: 40px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .btn-view i, 
            .btn-cancel i {
                font-size: 1rem;
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
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Requests Status</h1>
                    <p class="mb-0">Check the status of your animal control requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (!$hasAnyRequests): ?>
            <div class="no-requests-message">
                <i class="fas fa-inbox"></i>
                <h4>No Requests Found</h4>
                <p>You haven't submitted any requests yet.</p>
            </div>
        <?php else: ?>
            <!-- Only show sections that have requests -->
            <?php if (!empty($claims)): ?>
                <div class="status-card card">
                    <div class="card-header">
                        <h4 class="mb-0">Dog Claims</h4>
                    </div>
                    <div class="card-body p-0">
                        <!-- Desktop Table -->
                        <div class="table-responsive desktop-table">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th class="table-col-id">ID</th>
                                        <th class="table-col-name">Full Name</th>
                                        <th class="table-col-status">Status</th>
                                        <th class="table-col-actions"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claims as $claim): ?>
                                    <tr>
                                        <td class="table-col-id"><?= $claim['id'] ?></td>
                                        <td class="table-col-name"><?= htmlspecialchars($claim['full_name']) ?></td>
                                        <td class="table-col-status">
                                            <span class="status-badge 
                                                <?= $claim['status'] === 'approved' ? 'bg-approved' : 
                                                ($claim['status'] === 'declined' ? 'bg-declined' : 
                                                ($claim['status'] === 'completed' ? 'bg-completed' : 
                                                ($claim['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?>">
                                                <?= ucfirst($claim['status']) ?>
                                            </span>
                                        </td>
                                        <td class="table-col-actions">
                                            <div class="action-buttons">
                                                <?php if ($claim['status'] === 'pending'): ?>
                                                    <button class="btn btn-cancel cancel-btn" 
                                                            data-id="<?= $claim['id'] ?>" data-type="claim">
                                                        <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-view view-claim-btn" data-id="<?= $claim['id'] ?>">
                                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Table -->
                        <div class="mobile-table">
                            <?php foreach ($claims as $claim): ?>
                            <div class="mobile-row">
                                <div>
                                    <span class="mobile-label">ID:</span>
                                    <?= $claim['id'] ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Name:</span>
                                    <?= htmlspecialchars($claim['full_name']) ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Status:</span>
                                    <span class="status-badge 
                                        <?= $claim['status'] === 'approved' ? 'bg-approved' : 
                                        ($claim['status'] === 'declined' ? 'bg-declined' : 
                                        ($claim['status'] === 'completed' ? 'bg-completed' : 
                                        ($claim['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?>">
                                        <?= ucfirst($claim['status']) ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="action-buttons">
                                        <button class="btn btn-view view-claim-btn" data-id="<?= $claim['id'] ?>">
                                            <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                        </button>
                                        <?php if ($claim['status'] === 'pending'): ?>
                                            <button class="btn btn-cancel cancel-btn" 
                                                    data-id="<?= $claim['id'] ?>" data-type="claim">
                                                <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($adoptions)): ?>
                <div class="status-card card">
                    <div class="card-header">
                        <h4 class="mb-0">Dog Adoptions</h4>
                    </div>
                    <div class="card-body p-0">
                        <!-- Desktop Table -->
                        <div class="table-responsive desktop-table">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th class="table-col-id">ID</th>
                                        <th class="table-col-name">Full Name</th>
                                        <th class="table-col-status">Status</th>
                                        <th class="table-col-actions"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adoptions as $adoption): ?>
                                    <tr>
                                        <td class="table-col-id"><?= $adoption['id'] ?></td>
                                        <td class="table-col-name"><?= htmlspecialchars($adoption['full_name']) ?></td>
                                        <td class="table-col-status">
                                            <span class="status-badge 
                                                <?= $adoption['status'] === 'approved' ? 'bg-approved' : 
                                                ($adoption['status'] === 'declined' ? 'bg-declined' : 
                                                ($adoption['status'] === 'completed' ? 'bg-completed' : 
                                                ($adoption['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?>">
                                                <?= ucfirst($adoption['status']) ?>
                                            </span>
                                        </td>
                                        <td class="table-col-actions">
                                            <div class="action-buttons">
                                                <?php if ($adoption['status'] === 'pending'): ?>
                                                    <button class="btn btn-cancel cancel-btn" 
                                                            data-id="<?= $adoption['id'] ?>" data-type="adoption">
                                                        <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-view view-adoption-btn" data-id="<?= $adoption['id'] ?>">
                                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Table -->
                        <div class="mobile-table">
                            <?php foreach ($adoptions as $adoption): ?>
                            <div class="mobile-row">
                                <div>
                                    <span class="mobile-label">ID:</span>
                                    <?= $adoption['id'] ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Name:</span>
                                    <?= htmlspecialchars($adoption['full_name']) ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Status:</span>
                                    <span class="status-badge 
                                        <?= $adoption['status'] === 'approved' ? 'bg-approved' : 
                                        ($adoption['status'] === 'declined' ? 'bg-declined' : 
                                        ($adoption['status'] === 'completed' ? 'bg-completed' : 
                                        ($adoption['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?>">
                                        <?= ucfirst($adoption['status']) ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="action-buttons">
                                        <?php if ($adoption['status'] === 'pending'): ?>
                                            <button class="btn btn-cancel cancel-btn" 
                                                    data-id="<?= $adoption['id'] ?>" data-type="adoption">
                                                <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-view view-adoption-btn" data-id="<?= $adoption['id'] ?>">
                                            <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($reports)): ?>
                <div class="status-card card">
                    <div class="card-header">
                        <h4 class="mb-0">Rabid Reports</h4>
                    </div>
                    <div class="card-body p-0">
                        <!-- Desktop Table -->
                        <div class="table-responsive desktop-table">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th class="table-col-id">ID</th>
                                        <th class="table-col-name">Full Name</th>
                                        <th class="table-col-status">Status</th>
                                        <th class="table-col-actions"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td class="table-col-id"><?= $report['id'] ?></td>
                                        <td class="table-col-name"><?= htmlspecialchars($report['full_name']) ?></td>
                                        <td class="table-col-status">
                                            <span class="status-badge 
                                                <?= $report['status'] === 'verified' ? 'bg-verified' : 
                                                ($report['status'] === 'false_report' ? 'bg-false-report' : 
                                                ($report['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')) ?>">
                                                <?= str_replace('_', ' ', ucfirst($report['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="table-col-actions">
                                            <div class="action-buttons">
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <button class="btn btn-cancel cancel-btn" 
                                                            data-id="<?= $report['id'] ?>" data-type="report">
                                                        <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-view view-report-btn" data-id="<?= $report['id'] ?>">
                                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Table -->
                        <div class="mobile-table">
                            <?php foreach ($reports as $report): ?>
                            <div class="mobile-row">
                                <div>
                                    <span class="mobile-label">ID:</span>
                                    <?= $report['id'] ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Name:</span>
                                    <?= htmlspecialchars($report['full_name']) ?>
                                </div>
                                <div>
                                    <span class="mobile-label">Status:</span>
                                    <span class="status-badge 
                                        <?= $report['status'] === 'verified' ? 'bg-verified' : 
                                        ($report['status'] === 'false_report' ? 'bg-false-report' : 
                                        ($report['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending')) ?>">
                                        <?= str_replace('_', ' ', ucfirst($report['status'])) ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="action-buttons">
                                        <?php if ($report['status'] === 'pending'): ?>
                                            <button class="btn btn-cancel cancel-btn" 
                                                    data-id="<?= $report['id'] ?>" data-type="report">
                                                <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cancel</span>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-view view-report-btn" data-id="<?= $report['id'] ?>">
                                            <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- View Request Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
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

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this request?</p>
                    <form id="cancelForm" method="POST">
                        <input type="hidden" name="request_id" id="cancelRequestId">
                        <input type="hidden" name="request_type" id="cancelRequestType">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="submit" form="cancelForm" class="btn btn-danger">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Cancellation Modal -->
    <div class="modal fade" id="successCancelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Request Cancelled</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-danger mb-4"></i>
                    <h4>Request Cancelled Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="continueAfterCancel">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="imageModalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex justify-content-center align-items-center">
                    <img id="fullscreenImage" class="img-fluid" style="max-height: 90vh; max-width: 100%; object-fit: contain;">
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <a id="imageDownload" class="btn btn-primary" download>
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // View Claim Details
        $('.view-claim-btn').click(function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: 'view_status.php',
                method: 'GET',
                data: {
                    id: requestId,
                    type: 'claim',
                    user_view: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>
                },
                success: function(data) {
                    $('#viewModalContent').html(data);
                },
                error: function(xhr, status, error) {
                    $('#viewModalContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Failed to load claim details. Please try again.
                            ${xhr.responseJSON?.error ? '<br><small>' + xhr.responseJSON.error + '</small>' : ''}
                        </div>
                    `);
                }
            });
        });

        // View Adoption Details
        $('.view-adoption-btn').click(function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: 'view_status.php',
                method: 'GET',
                data: {
                    id: requestId,
                    type: 'adoption',
                    user_view: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>
                },
                success: function(data) {
                    $('#viewModalContent').html(data);
                },
                error: function(xhr) {
                    $('#viewModalContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Failed to load adoption details. Please try again.
                            ${xhr.responseJSON?.error ? '<br><small>' + xhr.responseJSON.error + '</small>' : ''}
                        </div>
                    `);
                }
            });
        });

        // View Report Details
        $('.view-report-btn').click(function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: 'view_status.php',
                method: 'GET',
                data: {
                    id: requestId,
                    type: 'report',
                    user_view: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>
                },
                success: function(data) {
                    $('#viewModalContent').html(data);
                },
                error: function(xhr) {
                    $('#viewModalContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Failed to load report details. Please try again.
                            ${xhr.responseJSON?.error ? '<br><small>' + xhr.responseJSON.error + '</small>' : ''}
                        </div>
                    `);
                }
            });
        });

        // Image viewing handler
        $(document).on('click', '.document-view', function(e) {
            e.preventDefault();
            const src = $(this).data('src');
            const title = $(this).data('title');
            
            $('#fullscreenImage').attr('src', src);
            $('#imageDownload').attr('href', src);
            $('#imageModalTitle').text(title);
            $('#imageModal').modal('show');
        });

        // Cancel functionality
        $('.cancel-btn').click(function() {
            const requestId = $(this).data('id');
            const requestType = $(this).data('type');
            
            $('#cancelRequestId').val(requestId);
            $('#cancelRequestType').val(requestType);
            $('#cancelModal').modal('show');
        });

        $('#cancelForm').submit(function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: 'status.php',
                type: 'POST',
                data: form.serialize() + '&cancel_request=1',
                success: function(response) {
                    $('#cancelModal').modal('hide');
                    const successModal = new bootstrap.Modal('#successCancelModal');
                    successModal.show();
                    
                    $('#continueAfterCancel').off().click(function() {
                        successModal.hide();
                        location.reload();
                    });
                },
                error: function(xhr, status, error) {
                    $('#cancelModal').modal('hide');
                    alert('Error cancelling request. Please try again.');
                    console.error('Error:', error);
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Yes, Cancel');
                }
            });
        });
    });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>