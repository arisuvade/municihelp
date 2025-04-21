<?php
session_start();
include '../includes/db.php';

// check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['error' => 'Invalid request ID']));
}

$request_id = (int)$_GET['id'];
$is_user_view = isset($_GET['user_view']);

$stmt = $conn->prepare("
    SELECT ar.*, at.name as program, at.specific_requirement, 
           u.phone as applicant_phone,
           ar.status, ar.note, b.name as barangay_name
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    JOIN users u ON ar.user_id = u.id
    JOIN barangays b ON ar.barangay_id = b.id
    WHERE ar.id = ?
");

if (!$stmt) {
    die(json_encode(['error' => 'Database error: ' . $conn->error]));
}

$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    die(json_encode(['error' => 'Request not found']));
}

// for user view, verify the request belongs to them
if ($is_user_view && $request['user_id'] != $_SESSION['user_id']) {
    die(json_encode(['error' => 'Unauthorized access to this request']));
}

function isImage($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $imageExtensions);
}

function getStatusBadge($status) {
    switch($status) {
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'completed':
            return '<span class="badge bg-primary">Completed</span>';
        case 'declined':
            return '<span class="badge bg-danger">Declined</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        default:
            return '<span class="badge bg-secondary">'.$status.'</span>';
    }
}
?>

<div class="container-fluid">
    <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
        <p><strong>Status:</strong> <?= getStatusBadge($request['status']) ?></p>
        <p><strong>Program:</strong> <?= htmlspecialchars($request['program']) ?></p>
        <p><strong>Applicant Name:</strong> <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></p>
        <p><strong>Applicant Contact No.:</strong> <?= htmlspecialchars($request['applicant_phone']) ?></p>
        <p><strong>Barangay:</strong> <?= htmlspecialchars($request['barangay_name']) ?></p> <!-- Now showing barangay name -->
        <p><strong>Complete Address:</strong> <?= htmlspecialchars($request['complete_address']) ?></p>
        <p><strong>Date Submitted:</strong> <?= date('M d, Y h:i A', strtotime($request['created_at'])) ?></p>
        <?php if (!empty($request['note'])): ?>
            <p><strong>Note:</strong> <?= htmlspecialchars($request['note']) ?></p>
        <?php endif; ?>
        <?php if ($request['status'] == 'approved'): ?>
            <p><strong>Queue No.:</strong> <?= htmlspecialchars($request['queue_number']) ?></p>
            <p><strong>Queue Date:</strong> <?= htmlspecialchars($request['queue_date']) ?></p>
        <?php endif; ?>
    </div>

    <div class="row">
        <?php 
        $documents = [
            [
                'path' => $request['specific_request_path'],
                'title' => htmlspecialchars($request['specific_requirement']),
                'required' => true
            ],
            [
                'path' => $request['indigency_cert_path'],
                'title' => 'Brgy. Indigency Certificate',
                'required' => true
            ],
            [
                'path' => $request['id_copy_path'],
                'title' => 'Xerox ng ID na may address ng Pulilan',
                'required' => true
            ],
            [
                'path' => $request['request_letter_path'],
                'title' => 'Sulat kahilingan na nakapangalan kay Vice Mayor RJ Peralta',
                'required' => true
            ]
        ];
        
        foreach ($documents as $doc): ?>
            <div class="col-md-6 mb-4">
                <div class="document-card h-100 bg-white rounded shadow-sm overflow-hidden">
                    <div class="document-header bg-light p-3 border-bottom">
                        <h6 class="mb-0"><?= $doc['title'] ?></h6>
                    </div>
                    <div class="document-body p-3 text-center">
                        <?php if (!empty($doc['path'])): ?>
                            <?php if (isImage($doc['path'])): ?>
                                <img src="../<?= htmlspecialchars($doc['path']) ?>" 
                                     class="img-fluid" 
                                     style="max-height: 200px; cursor: pointer;"
                                     onclick="viewDocument('../<?= htmlspecialchars($doc['path']) ?>', 'image', '<?= $doc['title'] ?>')">
                            <?php else: ?>
                                <div class="text-center py-4" 
                                     onclick="viewDocument('../<?= htmlspecialchars($doc['path']) ?>', 'file', '<?= $doc['title'] ?>')"
                                     style="cursor: pointer;">
                                    <i class="fas fa-file-alt fa-4x text-primary mb-2"></i>
                                    <p>View Document</p>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="../<?= htmlspecialchars($doc['path']) ?>" 
                                   class="btn btn-sm btn-primary" download>
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted py-4">No document uploaded</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function viewDocument(path, type, title) {
    $('#documentModalTitle').text(title);
    
    $('#documentImage').hide();
    $('#documentPdf').hide();
    $('#documentUnsupported').hide();
    
    // download link
    $('#documentDownload').attr('href', path);
    
    if (type === 'image') {
        $('#documentImage').attr('src', path).show();
    } else {
        $('#documentUnsupported').show();
    }
    
    $('#documentModal').modal('show');
}
</script>