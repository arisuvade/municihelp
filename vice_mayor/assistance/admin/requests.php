<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['assistance_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$assistance_admin_id = $_SESSION['assistance_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $assistance_admin_id");
$admin_data = $admin_query->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

$pageTitle = 'All Requests';
include '../../../includes/header.php';

function formatPhoneNumber($phone) {
    if (empty($phone)) return 'Walk-in';
    if (strpos($phone, '+63') === 0) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

function calculateAge($birthday) {
    if (empty($birthday)) return 'N/A';
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age . ' years old';
}

// Get all programs for filter dropdown
$programsQuery = "SELECT id, name, parent_id FROM assistance_types ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, name";
$programs = $conn->query($programsQuery);

// Store programs in an array for later use
$programsArray = [];
while ($program = $programs->fetch_assoc()) {
    $programsArray[] = $program;
}

// Reset pointer for dropdown display
$programs->data_seek(0);

// Get all requests (regular and walk-ins)
$query = "
    SELECT ar.id, at.name as program, at.parent_id,
           ar.last_name, ar.first_name, ar.middle_name, ar.birthday,
           u.phone, ar.created_at, ar.status,
           b.name as barangay, ar.is_walkin,
           a.name as walkin_admin_name
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    LEFT JOIN users u ON ar.user_id = u.id
    LEFT JOIN barangays b ON ar.barangay_id = b.id
    LEFT JOIN admins a ON ar.walkin_admin_id = a.id
    ORDER BY ar.id DESC";

$result = $conn->query($query);

$barangays = $conn->query("SELECT id, name FROM barangays ORDER BY name");

$statuses = ['pending', 'approved', 'declined', 'completed', 'cancelled'];
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
        
        .bg-completed {
            background-color: var(--completed) !important;
            color: white;
        }
        
        .bg-declined {
            background-color: var(--declined) !important;
            color: white;
        }
        
        .bg-cancelled {
            background-color: var(--cancelled) !important;
            color: white;
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
            background-color: #0E3B85;
            color: white;
        }
        
        .no-requests {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-requests i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        .no-requests h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-requests p {
            font-size: 1rem;
            color: #6c757d;
        }
        
        /* Column widths */
        .table-col-id {
            width: 6%;
            text-align: center;
        }
        
        .table-col-name {
            width: 18%;
        }
        
        .table-col-age {
            width: 10%;
            text-align: center;
        }
        
        .table-col-program {
            width: 14%;
            text-align: center;
        }
        
        .table-col-phone {
            width: 10%;
            text-align: center;
        }
        
        .table-col-barangay {
            width: 12%;
            text-align: center;
        }
        
        .table-col-date {
            width: 10%;
            text-align: center;
        }
        
        .table-col-status {
            width: 12%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 8%;
            text-align: center;
        }
        
        .walkin-badge {
            background-color: #6c757d;
            color: white;
            padding: 0.25em 0.4em;
            font-size: 0.75em;
            border-radius: 3px;
            margin-left: 5px;
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
                    <h1 class="mb-1">All Requests</h1>
                    <p class="mb-0">View and manage all requests</p>
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
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchFilter" 
                            placeholder="Search by ID, name or contact">
                    </div>
                    
                    <div class="filter-group">
                        <label for="programFilter" class="form-label">Program</label>
                        <select class="form-select" id="programFilter">
                            <option value="">All Programs</option>
                            <?php 
                            $currentParent = null;
                            foreach ($programsArray as $program): 
                                if ($program['parent_id'] === null) {
                                    $currentParent = $program['id'];
                                    ?>
                                    <option value="<?= htmlspecialchars($program['name']) ?>">
                                        <?= htmlspecialchars($program['name']) ?>
                                    </option>
                                    <?php
                                    // Find and display children
                                    foreach ($programsArray as $child) {
                                        if ($child['parent_id'] == $currentParent) {
                                            ?>
                                            <option value="<?= htmlspecialchars($child['name']) ?>">
                                                &nbsp;&nbsp;&#8627; <?= htmlspecialchars($child['name']) ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                }
                            endforeach; 
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="statusFilter" class="form-label">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status ?>">
                                    <?= ucfirst($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="barangayFilter" class="form-label">Barangay</label>
                        <select class="form-select" id="barangayFilter">
                            <option value="">All Barangays</option>
                            <?php while ($barangay = $barangays->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($barangay['name']) ?>">
                                    <?= htmlspecialchars($barangay['name']) ?>
                                </option>
                            <?php endwhile; ?>
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
            <?php if ($result->num_rows == 0): ?>
                <!-- Initial no requests message -->
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>There are no assistance requests to display.</p>
                </div>
            <?php else: ?>
                <!-- This div will be shown/hidden as needed when filtering -->
                <div class="no-requests" id="noResultsMessage" style="display: none;">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>No requests match your current filters.</p>
                </div>

                <!-- Wrap the entire table structure in a container -->
                <div id="requestsTableContainer">
                    <div class="recent-activity-card card mb-4">
                        <div class="card-body p-0">
                            <!-- Desktop Table -->
                            <div class="table-responsive desktop-table">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="table-col-id">ID</th>
                                            <th class="table-col-name">Name</th>
                                            <th class="table-col-age">Age</th>
                                            <th class="table-col-program">Program</th>
                                            <th class="table-col-phone">Contact No.</th>
                                            <th class="table-col-barangay">Barangay</th>
                                            <th class="table-col-date">Date Submitted</th>
                                            <th class="table-col-status">Status</th>
                                            <th class="table-col-actions"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="requestsTableBody">
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                            $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                            $phone_number = formatPhoneNumber($row['phone']);
                                            $age = calculateAge($row['birthday']);
                                            $is_walkin = $row['is_walkin'] == 1;
                                            
                                            // Get parent program name if this is a child program
                                            $parent_program = null;
                                            if ($row['parent_id']) {
                                                foreach ($programsArray as $program) {
                                                    if ($program['id'] == $row['parent_id']) {
                                                        $parent_program = $program['name'];
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                            <tr class="request-row" 
                                                data-id="<?= $row['id'] ?>"
                                                data-name="<?= htmlspecialchars(strtolower($row['last_name'] . ' ' . $row['first_name'])) ?>"
                                                data-program="<?= htmlspecialchars($row['program']) ?>"
                                                data-parent-program="<?= htmlspecialchars($parent_program ?? '') ?>"
                                                data-status="<?= htmlspecialchars($row['status']) ?>"
                                                data-barangay="<?= htmlspecialchars($row['barangay'] ?? '') ?>"
                                                data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>"
                                                data-phone="<?= htmlspecialchars($phone_number) ?>"
                                                data-is-walkin="<?= $is_walkin ? '1' : '0' ?>">
                                                <td class="table-col-id"><?= $row['id'] ?></td>
                                                <td class="table-col-name"><?= $applicant_name ?></td>
                                                <td class="table-col-age"><?= $age ?></td>
                                                <td class="table-col-program"><?= htmlspecialchars($row['program']) ?></td>
                                                <td class="table-col-phone"><?= htmlspecialchars($phone_number) ?></td>
                                                <td class="table-col-barangay"><?= htmlspecialchars($row['barangay'] ?? 'N/A') ?></td>
                                                <td class="table-col-date"><?= date('F j, Y', strtotime($row['created_at'])) ?></td>
                                                <td class="table-col-status">
                                                    <span class="status-badge 
                                                        <?= $row['status'] === 'approved' ? 'bg-approved' : 
                                                        ($row['status'] === 'declined' ? 'bg-declined' : 
                                                        ($row['status'] === 'completed' ? 'bg-completed' : 
                                                        ($row['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?>">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="table-col-actions">
                                                    <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile Table -->
                            <div class="mobile-table" id="mobileRequestsTable">
                                <?php 
                                $result->data_seek(0); // Reset pointer to start
                                while ($row = $result->fetch_assoc()): 
                                    $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                                    $applicant_name = htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $middle_initial);
                                    $phone_number = formatPhoneNumber($row['phone']);
                                    $age = calculateAge($row['birthday']);
                                    $is_walkin = $row['is_walkin'] == 1;
                                    
                                    // Get parent program name if this is a child program
                                    $parent_program = null;
                                    if ($row['parent_id']) {
                                        foreach ($programsArray as $program) {
                                            if ($program['id'] == $row['parent_id']) {
                                                $parent_program = $program['name'];
                                                break;
                                            }
                                        }
                                    }
                                ?>
                                    <div class="mobile-row request-row" 
                                        data-id="<?= $row['id'] ?>"
                                        data-name="<?= htmlspecialchars(strtolower($row['last_name'] . ' ' . $row['first_name'])) ?>"
                                        data-program="<?= htmlspecialchars($row['program']) ?>"
                                        data-parent-program="<?= htmlspecialchars($parent_program ?? '') ?>"
                                        data-status="<?= htmlspecialchars($row['status']) ?>"
                                        data-barangay="<?= htmlspecialchars($row['barangay'] ?? '') ?>"
                                        data-date="<?= date('Y-m-d', strtotime($row['created_at'])) ?>"
                                        data-phone="<?= htmlspecialchars($phone_number) ?>"
                                        data-is-walkin="<?= $is_walkin ? '1' : '0' ?>">
                                        <div>
                                            <span class="mobile-label">ID:</span>
                                            <?= $row['id'] ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Name:</span>
                                            <?= $applicant_name ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Age:</span>
                                            <?= $age ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Program:</span>
                                            <?= htmlspecialchars($row['program']) ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Contact:</span>
                                            <?= htmlspecialchars($phone_number) ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Barangay:</span>
                                            <?= htmlspecialchars($row['barangay'] ?? 'N/A') ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Date Submitted:</span>
                                            <?= date('F j, Y', strtotime($row['created_at'])) ?>
                                        </div>
                                        <div>
                                            <span class="mobile-label">Status:</span>
                                            <span class="status-badge 
                                                <?= $row['status'] === 'approved' ? 'bg-approved' : 
                                                ($row['status'] === 'declined' ? 'bg-declined' : 
                                                ($row['status'] === 'completed' ? 'bg-completed' : 
                                                ($row['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?>">
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <button class="btn btn-view view-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- view requirements modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewModalContent">
                    <!-- content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- document viewer modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalTitle">Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="documentImage" src="" class="img-fluid" style="max-height: 80vh;" alt="Document">
                    <iframe id="documentPdf" src="" style="width: 100%; height: 80vh; display: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // View Requirements
        $(document).on('click', '.view-btn', function() {
            const requestId = $(this).data('id');
            $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
            $('#viewModal').modal('show');
            
            $.ajax({
                url: '../../../vice_mayor/assistance/view_request.php?id=' + requestId,
                method: 'GET',
                success: function(data) {
                    $('#viewModalContent').html(data);
                    
                    $('.document-view').click(function(e) {
                        e.preventDefault();
                        const src = $(this).data('src');
                        const type = $(this).data('type');
                        const title = $(this).data('title');
                        
                        $('#documentModalTitle').text(title);
                        
                        if (type === 'image') {
                            $('#documentImage').attr('src', src).show();
                            $('#documentPdf').hide();
                        } else {
                            $('#documentPdf').attr('src', src).show();
                            $('#documentImage').hide();
                        }
                        
                        $('#documentModal').modal('show');
                    });
                },
                error: function() {
                    $('#viewModalContent').html('<div class="alert alert-danger">Failed to load request details</div>');
                }
            });
        });

        // Filter function
        function applyFilters() {
            const searchTerm = $('#searchFilter').val().toLowerCase();
            const programFilter = $('#programFilter').val();
            const statusFilter = $('#statusFilter').val();
            const barangayFilter = $('#barangayFilter').val();
            const dateFilter = $('#dateFilter').val();
            
            let anyVisible = false;
            
            $('.request-row').each(function() {
                const $row = $(this);
                const id = $row.data('id').toString();
                const name = $row.data('name');
                const program = $row.data('program');
                const parentProgram = $row.data('parent-program');
                const status = $row.data('status');
                const barangay = $row.data('barangay');
                const date = $row.data('date');
                const phone = $row.data('phone');
                const isWalkin = $row.data('is-walkin');
                
                const matchesSearch = !searchTerm || 
                    name.includes(searchTerm) || 
                    id.includes(searchTerm) || 
                    phone.includes(searchTerm);
                
                const matchesProgram = !programFilter || 
                    program === programFilter || 
                    parentProgram === programFilter;
                
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesBarangay = !barangayFilter || barangay === barangayFilter;
                const matchesDate = !dateFilter || date === dateFilter;
                
                if (matchesSearch && matchesProgram && matchesStatus && matchesBarangay && matchesDate) {
                    $row.show();
                    anyVisible = true;
                } else {
                    $row.hide();
                }
            });
            
            const noResultsMsg = $('#noResultsMessage');
            const tableContainer = $('#requestsTableContainer');
            
            if (!anyVisible) {
                noResultsMsg.show();
                tableContainer.hide();
            } else {
                noResultsMsg.hide();
                tableContainer.show();
            }
        }
        
        // Apply filters when any filter changes
        $('#searchFilter, #programFilter, #statusFilter, #barangayFilter, #dateFilter').on('input change', function() {
            applyFilters();
        });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>