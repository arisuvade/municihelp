<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../includes/auth/login.php');
    exit;
}

// get all requests sorted newest first
$stmt = $conn->prepare("
    SELECT r.id, a.name as program, 
           CONCAT(r.last_name, ', ', r.first_name, ' ', IFNULL(r.middle_name, '')) as full_name,
           r.created_at, r.status, r.note
    FROM assistance_requests r
    JOIN assistance_types a ON r.assistance_id = a.id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$all_requests = $result->fetch_all(MYSQLI_ASSOC);

$page_title = "Request Status";
include '../includes/header.php';
?>

<style>
    :root {
        --munici-green: #4CAF50;
        --munici-green-light: #E8F5E9;
    }
    
    body {
        background-color: #f5f7fb;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .dashboard-header {
        background: linear-gradient(135deg, var(--munici-green), #2E7D32);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .status-card {
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border: none;
        margin-bottom: 2rem;
    }
    
    .status-card .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
        font-size: 1.2rem;
        padding: 1.25rem 1.5rem;
        border-radius: 10px 10px 0 0 !important;
    }
    
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border-top: none;
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
    }
    
    .table td {
        padding: 1rem 1.5rem;
        vertical-align: middle;
        border-top: 1px solid rgba(0, 0, 0, 0.03);
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    
    .bg-pending {
        background-color: #FFC107 !important;
        color: #212529;
    }
    
    .bg-approved {
        background-color: #28A745 !important;
    }
    
    .bg-declined {
        background-color: #DC3545 !important;
    }
    
    .bg-completed {
        background-color: #007BFF !important;
    }
    
    .bg-cancelled {
        background-color: #6C757D !important;
    }
    
    .btn-view {
        background-color: #4361ee;
        color: white;
        border: none;
    }
    
    .btn-view:hover {
        background-color: #3a56d4;
        color: white;
    }
    
    .btn-edit {
        background-color: #4CAF50;
        color: white;
        border: none;
    }
    
    .btn-edit:hover {
        background-color: #3e8e41;
        color: white;
    }
</style>

<div class="dashboard-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Request Status</h1>
                <p class="mb-0">Tingnan ang status ng iyong mga kahilingan para sa tulong.</p>
            </div>
            <button class="btn btn-light" onclick="location.href='index.php'">
                <i class="fas fa-plus"></i> New Request
            </button>
        </div>
    </div>
</div>

<div class="container">
    <div class="status-card card">
        <div class="card-body">
            <?php if (empty($all_requests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <h5>Hindi ka pa nagsusumite ng anumang kahilingan.</h5>
                    <p class="text-muted">I-click ang button na "New Request" upang magsimula.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Program</th>
                                <th>Full Name</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th>Note</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_requests as $request): ?>
                            <tr>
                                <td><?= $request['id'] ?></td>
                                <td><?= htmlspecialchars($request['program']) ?></td>
                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <span class="badge 
                                        <?= $request['status'] === 'approved' ? 'bg-approved' : 
                                        ($request['status'] === 'declined' ? 'bg-declined' : 
                                        ($request['status'] === 'completed' ? 'bg-completed' : 
                                        ($request['status'] === 'cancelled' ? 'bg-cancelled' : 'bg-pending'))) ?> 
                                        status-badge">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($request['note'])): ?>
                                        <small><?= nl2br(htmlspecialchars($request['note'])) ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-view view-btn" data-id="<?= $request['id'] ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-edit edit-btn" 
                                                data-request-id="<?= $request['id'] ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
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

<!-- Document Viewer Modal -->
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
    // view Request Details
    $('.view-btn').click(function() {
        const requestId = $(this).data('id');
        $('#viewModalContent').html('<div class="text-center my-5"><div class="spinner-border" role="status"></div></div>');
        $('#viewModal').modal('show');
        
        $.ajax({
            url: '../includes/view_request.php?id=' + requestId,
            method: 'GET',
            success: function(data) {
                $('#viewModalContent').html(data);
                
                // handle document viewing in modal
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

    // edit Request
    $('.edit-btn').click(function() {
        const requestId = $(this).data('request-id');
        window.location.href = `edit_request.php?id=${requestId}`;
    });
});
</script>

<?php include '../includes/footer.php'; ?>