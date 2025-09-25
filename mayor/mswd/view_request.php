<?php
session_start();
include '../../includes/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['mswd_admin_id']) && !isset($_SESSION['mayor_admin_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['error' => 'Invalid request ID']));
}

$request_id = (int)$_GET['id'];
$is_user_view = isset($_SESSION['user_id']);

$stmt = $conn->prepare("
    SELECT mr.*, mt.name as program, 
           u.phone as user_phone, u.id as user_id, u.name as user_first_name, 
           u.middle_name as user_middle_name, u.last_name as user_last_name,
           u.birthday as user_birthday, u.address as user_address,
           u.is_verified as user_verified, u.created_at as user_created_at,
           u.barangay_id as user_barangay_id, ub.name as user_barangay_name,
           mr.status, mr.reason, b.name as barangay_name,
           fr.english_term as relation_english, fr.filipino_term as relation_filipino,
           walkin_admin.name as walkin_admin_name,
           approved_admin.name as approved_admin_name,
           approved2_admin.name as approved2_admin_name,
           completed_admin.name as completed_admin_name,
           declined_admin.name as declined_admin_name,
           cancelled_admin.name as cancelled_admin_name,
           rescheduled_admin.name as rescheduled_admin_name
    FROM mswd_requests mr
    LEFT JOIN mswd_types mt ON mr.assistance_id = mt.id
    LEFT JOIN users u ON mr.user_id = u.id
    LEFT JOIN barangays b ON mr.barangay_id = b.id
    LEFT JOIN barangays ub ON u.barangay_id = ub.id
    LEFT JOIN family_relations fr ON mr.relation_id = fr.id
    LEFT JOIN admins walkin_admin ON mr.walkin_admin_id = walkin_admin.id
    LEFT JOIN admins approved_admin ON mr.approvedby_admin_id = approved_admin.id
    LEFT JOIN admins approved2_admin ON mr.approved2by_admin_id = approved2_admin.id
    LEFT JOIN admins completed_admin ON mr.completedby_admin_id = completed_admin.id
    LEFT JOIN admins declined_admin ON mr.declinedby_admin_id = declined_admin.id
    LEFT JOIN admins cancelled_admin ON mr.cancelledby_admin_id = cancelled_admin.id
    LEFT JOIN admins rescheduled_admin ON mr.rescheduledby_admin_id = rescheduled_admin.id
    WHERE mr.id = ?
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

// For user view, verify the request belongs to them
if ($is_user_view && $request['user_id'] != $_SESSION['user_id']) {
    die(json_encode(['error' => 'Unauthorized access to this request']));
}

// Get the requirements for this assistance type
$requirements_stmt = $conn->prepare("
    SELECT id, name 
    FROM mswd_types_requirements 
    WHERE mswd_types_id = ? 
    ORDER BY id ASC
");
$requirements_stmt->bind_param("i", $request['assistance_id']);
$requirements_stmt->execute();
$requirements_result = $requirements_stmt->get_result();
$requirement_names = [];
while ($row = $requirements_result->fetch_assoc()) {
    $requirement_names[] = $row;
}

function isImage($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $imageExtensions);
}

function getStatusText($status) {
    $statusMap = [
        'pending' => 'Pending',
        'mswd_approved' => 'MSWD Approved',
        'mayor_approved' => 'Mayor Approved',
        'completed' => 'Completed',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled'
    ];
    return $statusMap[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

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

// Format user full name
$user_full_name = !empty($request['user_id']) ? htmlspecialchars(
    $request['user_first_name'] . ' ' . 
    (!empty($request['user_middle_name']) ? $request['user_middle_name'] . ' ' : '') . 
    $request['user_last_name']
) : '';

// Format requester full name
$requester_full_name = htmlspecialchars(
    $request['first_name'] . ' ' . 
    (!empty($request['middle_name']) ? $request['middle_name'] . ' ' : '') . 
    $request['last_name']
);

// Calculate ages
$user_age = !empty($request['user_birthday']) ? calculateAge($request['user_birthday']) : 'N/A';
$requester_age = !empty($request['birthday']) ? calculateAge($request['birthday']) : 'N/A';

// Format relation (show both English and Filipino terms)
$relation = '';
if (!empty($request['relation_english']) && !empty($request['relation_filipino'])) {
    $relation = htmlspecialchars($request['relation_english'] . ' (' . $request['relation_filipino'] . ')');
}

// Use assistance_name if available, otherwise use the program name from mswd_types
$program_name = !empty($request['assistance_name']) 
    ? htmlspecialchars($request['assistance_name']) 
    : htmlspecialchars($request['program']);

$is_walkin = $request['is_walkin'] == 1;

// Get all requirement paths with their actual names
$requirements = [];
foreach ($requirement_names as $index => $req) {
    $path_num = $index + 1; // requirement_path_1, requirement_path_2, etc.
    $path = $request["requirement_path_{$path_num}"];
    if (!empty($path)) {
        $requirements[] = [
            'path' => $path,
            'title' => htmlspecialchars($req['name']),
            'required' => true
        ];
    }
}

// Format phone numbers
$user_phone_formatted = !empty($request['user_phone']) ? formatPhoneNumber($request['user_phone']) : '';
$contact_phone_formatted = !empty($request['contact_no']) ? formatPhoneNumber($request['contact_no']) : '';
?>

<div class="container-fluid px-4">
    <!-- User Account Section -->
    <?php if (!empty($request['user_id'])): ?>
    <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
        <h4 class="border-bottom pb-2 mb-3">User Account</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Name:</strong> <?= $user_full_name ?>
                </div>
                <?php if (!empty($request['user_birthday'])): ?>
                <div class="mb-3">
                    <strong>Birthday:</strong> <?= date('F d, Y', strtotime($request['user_birthday'])) ?>
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
                <?php if (!empty($request['user_barangay_name'])): ?>
                <div class="mb-3">
                    <strong>Barangay:</strong> <?= htmlspecialchars($request['user_barangay_name']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($request['user_address'])): ?>
                <div class="mb-3">
                    <strong>Address:</strong> <?= htmlspecialchars($request['user_address']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Requester Information Section -->
    <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
        <h4 class="border-bottom pb-2 mb-3">Requester Information</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Name:</strong> <?= $requester_full_name ?>
                </div>
                <?php if (!empty($request['birthday'])): ?>
                <div class="mb-3">
                    <strong>Birthday:</strong> <?= date('F d, Y', strtotime($request['birthday'])) ?>
                </div>
                <div class="mb-3">
                    <strong>Age:</strong> <?= $requester_age ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($relation)): ?>
                <div class="mb-3">
                    <strong>Relation:</strong> <?= $relation ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <?php if (!empty($contact_phone_formatted)): ?>
                <div class="mb-3">
                    <strong>Contact No.:</strong> <?= htmlspecialchars($contact_phone_formatted) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($request['precint_number'])): ?>
                <div class="mb-3">
                    <strong>Precinct Number:</strong> <?= htmlspecialchars($request['precint_number']) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($request['barangay_name'])): ?>
                <div class="mb-3">
                    <strong>Barangay:</strong> <?= htmlspecialchars($request['barangay_name']) ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Complete Address:</strong> <?= htmlspecialchars($request['complete_address']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Information Section -->
    <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
        <h4 class="border-bottom pb-2 mb-3">Request Information</h4>
        <div class="row">
            <!-- LEFT SIDE -->
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Status:</strong> <?= getStatusText($request['status']) ?>
                </div>
                <div class="mb-3">
                    <strong>Program:</strong> <?= $program_name ?>
                </div>
                <?php if (!empty($request['remarks'])): ?>
<div class="mb-3">
    <strong>Remarks:</strong> <?= htmlspecialchars($request['remarks']) ?>
</div>
<?php endif; ?>
                <?php if (!empty($request['queue_no'])): ?>
                <div class="mb-3">
                    <strong>Queue Number:</strong> <?= htmlspecialchars($request['queue_no']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($request['queue_date'])): ?>
                <div class="mb-3">
                    <strong>Queue Date:</strong> <?= date('F d, Y', strtotime($request['queue_date'])) ?>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <strong>Submitted:</strong> <?= date('F d, Y h:i A', strtotime($request['created_at'])) ?>
                    <?php if ($is_walkin && !empty($request['walkin_admin_name'])): ?>
                        (by <?= htmlspecialchars($request['walkin_admin_name']) ?>)
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-6">
                <?php if ($request['status'] == 'mswd_approved' && !empty($request['approvedby_admin_id'])): ?>
                    <div class="mb-3"><strong>MSWD Approved by:</strong> <?= htmlspecialchars($request['approved_admin_name']) ?></div>
                    <div class="mb-3"><strong>MSWD Approved Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                <?php endif; ?>
                
                <?php if ($request['status'] == 'mayor_approved' && !empty($request['approved2by_admin_id'])): ?>
                    <div class="mb-3"><strong>Mayor Approved by:</strong> <?= htmlspecialchars($request['approved2_admin_name']) ?></div>
                    <div class="mb-3"><strong>Mayor Approved Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                <?php endif; ?>
                
                <?php if ($request['status'] == 'completed'): ?>
                    <div class="mb-3"><strong>Completed by:</strong> <?= htmlspecialchars($request['completed_admin_name']) ?></div>
                    <div class="mb-3"><strong>Released Date:</strong> <?= date('F d, Y', strtotime($request['released_date'])) ?></div>
                    <div class="mb-3">
                        <strong>Recipient:</strong> <?= htmlspecialchars($request['recipient']) ?>
                        <?php if (!empty($request['relation_to_recipient'])): ?>
                            (<?= htmlspecialchars($request['relation_to_recipient']) ?>)
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($request['amount'])): ?>
                    <div class="mb-3">
                        <strong>Amount:</strong> ₱<?= number_format($request['amount'], 2) ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($request['status'] == 'declined' && !empty($request['declinedby_admin_id'])): ?>
                    <div class="mb-3"><strong>Declined by:</strong> <?= htmlspecialchars($request['declined_admin_name']) ?></div>
                    <div class="mb-3"><strong>Declined Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                <?php endif; ?>
                
                <?php if ($request['status'] == 'cancelled'): ?>
                    <div class="mb-3"><strong>Cancelled by:</strong> 
                        <?= !empty($request['cancelledby_admin_id']) 
                            ? htmlspecialchars($request['cancelled_admin_name']) 
                            : 'User' ?>
                    </div>
                    <div class="mb-3"><strong>Cancelled Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                <?php endif; ?>
                
                <?php if ($request['reschedule_count'] > 0): ?>
                <div class="mb-3">
                    <strong>Rescheduled:</strong> <?= $request['reschedule_count'] ?> time(s)
                    <?php if (!empty($request['rescheduledby_admin_id'])): ?>
                        (last by <?= htmlspecialchars($request['rescheduled_admin_name']) ?>)
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($request['reason'])): ?>
                    <div class="mb-3"><strong>Reason:</strong> <?= !empty($request['reason']) ? htmlspecialchars($request['reason']) : 'No reason provided' ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($requirements)): ?>
    <div class="row">
        <?php foreach ($requirements as $doc): ?>
            <div class="col-12 mb-4">
                <div class="document-card h-100 bg-white rounded shadow-sm overflow-hidden">
                    <div class="document-header bg-light p-3 border-bottom">
                        <h6 class="mb-0"><?= $doc['title'] ?></h6>
                    </div>
                    <div class="document-body p-3 text-center">
                        <?php if (!empty($doc['path'])): ?>
                            <?php if (isImage($doc['path'])): ?>
                                <img src="../../../<?= htmlspecialchars($doc['path']) ?>" 
                                     class="img-fluid document-view" 
                                     style="max-height: 300px; cursor: pointer;"
                                     data-src="../../../<?= htmlspecialchars($doc['path']) ?>"
                                     data-title="<?= $doc['title'] ?>">
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-alt fa-4x text-primary mb-2"></i>
                                    <p>Document Uploaded</p>
                                    <a href="../../../<?= htmlspecialchars($doc['path']) ?>" 
                                       class="btn btn-sm btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted py-4">No document uploaded</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Fullscreen Image Modal -->
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

<script>
$(document).ready(function() {
    // Handle image viewing
    $('.document-view').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const src = $(this).data('src');
        const title = $(this).data('title');
        
        $('#fullscreenImage').attr('src', src);
        $('#imageDownload').attr('href', src);
        $('#imageModalTitle').text(title);
        $('#imageModal').modal('show');
    });
});
</script>