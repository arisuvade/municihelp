<?php
session_start();
include '../../includes/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['assistance_admin_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['error' => 'Invalid request ID']));
}

// Function to convert +639 format to 09 format
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

$request_id = (int)$_GET['id'];
$is_user_view = isset($_GET['user_view']);

$stmt = $conn->prepare("
    SELECT ar.*, at.name as program, at.specific_requirement, 
           u.phone as user_phone, u.id as user_id, u.name as user_first_name, 
           u.middle_name as user_middle_name, u.last_name as user_last_name,
           u.birthday as user_birthday, u.address as user_address,
           u.is_verified as user_verified, u.created_at as user_created_at,
           u.barangay_id as user_barangay_id, ub.name as user_barangay_name,
           ar.status, ar.reason, b.name as barangay_name,
           fr.english_term as relation_english, fr.filipino_term as relation_filipino,
           walkin_admin.name as walkin_admin_name,
           approved_admin.name as approved_admin_name,
           completed_admin.name as completed_admin_name,
           declined_admin.name as declined_admin_name,
           cancelled_admin.name as cancelled_admin_name,
           rescheduled_admin.name as rescheduled_admin_name
    FROM assistance_requests ar
    JOIN assistance_types at ON ar.assistance_id = at.id
    LEFT JOIN users u ON ar.user_id = u.id
    LEFT JOIN barangays b ON ar.barangay_id = b.id
    LEFT JOIN barangays ub ON u.barangay_id = ub.id
    LEFT JOIN family_relations fr ON ar.relation_id = fr.id
    LEFT JOIN admins walkin_admin ON ar.walkin_admin_id = walkin_admin.id
    LEFT JOIN admins approved_admin ON ar.approvedby_admin_id = approved_admin.id
    LEFT JOIN admins completed_admin ON ar.completedby_admin_id = completed_admin.id
    LEFT JOIN admins declined_admin ON ar.declinedby_admin_id = declined_admin.id
    LEFT JOIN admins cancelled_admin ON ar.cancelledby_admin_id = cancelled_admin.id
    LEFT JOIN admins rescheduled_admin ON ar.rescheduledby_admin_id = rescheduled_admin.id
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

// For user view, verify the request belongs to them
if ($is_user_view && $request['user_id'] != $_SESSION['user_id']) {
    die(json_encode(['error' => 'Unauthorized access to this request']));
}

function isImage($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $imageExtensions);
}

function getStatusText($status) {
    $statusMap = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled'
    ];
    return $statusMap[$status] ?? ucfirst($status);
}

function calculateAge($birthday) {
    if (empty($birthday)) return 'N/A';
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age . ' years old';
}

// Format user full name
$user_full_name = htmlspecialchars(
    $request['user_first_name'] . ' ' . 
    (!empty($request['user_middle_name']) ? $request['user_middle_name'] . ' ' : '') . 
    $request['user_last_name']
);

// Format requester full name
$requester_full_name = htmlspecialchars($request['first_name'] . ' ' . 
                      (!empty($request['middle_name']) ? $request['middle_name'] . ' ' : '') . 
                      $request['last_name']);

$requester_age = calculateAge($request['birthday']);
$user_age = calculateAge($request['user_birthday']);

// Format relation (show both English and Filipino terms)
$relation = '';
if (!empty($request['relation_english']) && !empty($request['relation_filipino'])) {
    $relation = htmlspecialchars($request['relation_english'] . ' (' . $request['relation_filipino'] . ')');
}

// Use assistance_name if available, otherwise use the program name from assistance_types
$program_name = !empty($request['assistance_name']) 
    ? htmlspecialchars($request['assistance_name']) 
    : htmlspecialchars($request['program']);

// Check if we need to show two ID copies (when relation is not Self)
$show_two_ids = ($request['relation_id'] != 1); // 1 is Self
$is_walkin = $request['is_walkin'] == 1;

// Format phone numbers
$user_phone_formatted = !empty($request['user_phone']) ? formatPhoneNumber($request['user_phone']) : '';
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
            </div>
            
            <div class="col-md-6">
                <?php if (!empty($relation)): ?>
                <div class="mb-3">
                    <strong>Relation:</strong> <?= $relation ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($request['barangay_name'])): ?>
                <div class="mb-3">
                    <strong>Barangay:</strong> <?= htmlspecialchars($request['barangay_name']) ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Address:</strong> <?= htmlspecialchars($request['complete_address']) ?>
                </div>
                
                <?php if (!empty($request['precint_number'])): ?>
                <div class="mb-3">
                    <strong>Precinct Number:</strong> <?= htmlspecialchars($request['precint_number']) ?>
                </div>
                <?php endif; ?>
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
<?php if (!empty($request['queue_date'])): ?>
                <div class="mb-3"><strong>Queue Date:</strong> <?= date('F d, Y', strtotime($request['queue_date'])) ?></div>
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
            <?php if ($request['status'] == 'approved' && !empty($request['approved_admin_name'])): ?>
                <div class="mb-3"><strong>Approved by:</strong> <?= htmlspecialchars($request['approved_admin_name']) ?></div>
                <div class="mb-3"><strong>Approved Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
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

            <?php if ($request['status'] == 'declined' && !empty($request['declined_admin_name'])): ?>
                <div class="mb-3"><strong>Declined by:</strong> <?= htmlspecialchars($request['declined_admin_name']) ?></div>
                <div class="mb-3"><strong>Declined Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
            <?php endif; ?>

            <?php if ($request['status'] == 'cancelled'): ?>
                <div class="mb-3"><strong>Cancelled by:</strong> 
                    <?= !empty($request['cancelled_admin_name']) 
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
                <div class="mb-3"><strong>Reason:</strong> <?= htmlspecialchars($request['reason']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <?php if (!$is_walkin): ?>
    <?php
    // Documents for online requests
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
            'path' => $request['request_letter_path'],
            'title' => 'Sulat kahilingan na nakapangalan kay Vice Mayor Atty. Imee Cruz',
            'required' => true
        ]
    ];
    
    // ID copies (one or two depending on relation)
    $id_documents = [
        [
            'path' => $request['id_copy_path'],
            'title' => 'Photocopy ng ID (Applicant)',
            'required' => true
        ]
    ];
    
    if ($show_two_ids && !empty($request['id_copy_path_2'])) {
        $id_documents[] = [
            'path' => $request['id_copy_path_2'],
            'title' => 'Photocopy ng ID (Requester)',
            'required' => true
        ];
    }
    
    // Combine all documents
    $all_documents = array_merge($documents, $id_documents);
    
    // Check if we have any documents with paths
    $has_documents = false;
    foreach ($all_documents as $doc) {
        if (!empty($doc['path'])) {
            $has_documents = true;
            break;
        }
    }
    ?>
    
    <?php if ($has_documents): ?>
    <div class="row">
        <?php foreach ($all_documents as $doc): 
            // Skip empty documents
            if (empty($doc['path'])) continue;
        ?>
            <div class="col-12 mb-4">
                <div class="document-card h-100 bg-white rounded shadow-sm overflow-hidden">
                    <div class="document-header bg-light p-3 border-bottom">
                        <h6 class="mb-0"><?= $doc['title'] ?></h6>
                    </div>
                    <div class="document-body p-3 text-center">
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
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
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