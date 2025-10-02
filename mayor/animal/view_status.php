<?php
session_start();
include '../../includes/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['animal_admin_id']) && !isset($_SESSION['pound_admin_id'])) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
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

function calculateAge($birthday) {
    if (empty($birthday)) return 'N/A';
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age . ' years old';
}

// Function to format offense count as ordinal
function formatOffenseCount($count) {
    if ($count == 1) {
        return '1st Offense';
    } elseif ($count == 2) {
        return '2nd Offense';
    } elseif ($count == 3) {
        return '3rd Offense';
    } else {
        return $count . 'th Offense';
    }
}

$request_id = (int)$_GET['id'];
$request_type = $_GET['type'] ?? '';
$is_user_view = isset($_GET['user_view']);

// Initialize variables
$request = null;
$dog_info = null;

try {
    switch ($request_type) {
        case 'claim':
            $stmt = $conn->prepare("
                SELECT c.*, d.id as dog_id, d.breed, d.color, d.size, d.gender, d.description as dog_description, 
                       d.location_found, d.date_caught, d.image_path as dog_image, d.status as dog_status,
                       approved_admin.name as approved_admin_name,
                       completed_admin.name as completed_admin_name,
                       declined_admin.name as declined_admin_name,
                       cancelled_admin.name as cancelled_admin_name,
                       b.name as barangay_name,
                       u.id as user_id, u.phone as user_phone, u.name as user_first_name, 
                       u.middle_name as user_middle_name, u.last_name as user_last_name,
                       u.birthday as user_birthday, u.address as user_address,
                       u.is_verified as user_verified, u.created_at as user_created_at,
                       u.barangay_id as user_barangay_id, ub.name as user_barangay_name
                FROM dog_claims c
                JOIN dogs d ON c.dog_id = d.id
                LEFT JOIN admins approved_admin ON c.approvedby_admin_id = approved_admin.id
                LEFT JOIN admins completed_admin ON c.completedby_admin_id = completed_admin.id
                LEFT JOIN admins declined_admin ON c.declinedby_admin_id = declined_admin.id
                LEFT JOIN admins cancelled_admin ON c.cancelledby_admin_id = cancelled_admin.id
                LEFT JOIN barangays b ON c.barangay_id = b.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN barangays ub ON u.barangay_id = ub.id
                WHERE c.id = ?
            ");
            break;
            
        case 'adoption':
            $stmt = $conn->prepare("
                SELECT a.*, d.id as dog_id, d.breed, d.color, d.size, d.gender, d.description as dog_description, 
                       d.location_found, d.date_caught, d.image_path as dog_image, d.status as dog_status,
                       approved_admin.name as approved_admin_name,
                       completed_admin.name as completed_admin_name,
                       declined_admin.name as declined_admin_name,
                       cancelled_admin.name as cancelled_admin_name,
                       b.name as barangay_name,
                       u.id as user_id, u.phone as user_phone, u.name as user_first_name, 
                       u.middle_name as user_middle_name, u.last_name as user_last_name,
                       u.birthday as user_birthday, u.address as user_address,
                       u.is_verified as user_verified, u.created_at as user_created_at,
                       u.barangay_id as user_barangay_id, ub.name as user_barangay_name
                FROM dog_adoptions a
                JOIN dogs d ON a.dog_id = d.id
                LEFT JOIN admins approved_admin ON a.approvedby_admin_id = approved_admin.id
                LEFT JOIN admins completed_admin ON a.completedby_admin_id = completed_admin.id
                LEFT JOIN admins declined_admin ON a.declinedby_admin_id = declined_admin.id
                LEFT JOIN admins cancelled_admin ON a.cancelledby_admin_id = cancelled_admin.id
                LEFT JOIN barangays b ON a.barangay_id = b.id
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN barangays ub ON u.barangay_id = ub.id
                WHERE a.id = ?
            ");
            break;
            
        case 'report':
            $stmt = $conn->prepare("
                SELECT r.*, 
                       verified_admin.name as verified_admin_name,
                       cancelled_admin.name as cancelled_admin_name,
                       b.name as barangay_name,
                       u.id as user_id, u.phone as user_phone, u.name as user_first_name, 
                       u.middle_name as user_middle_name, u.last_name as user_last_name,
                       u.birthday as user_birthday, u.address as user_address,
                       u.is_verified as user_verified, u.created_at as user_created_at,
                       u.barangay_id as user_barangay_id, ub.name as user_barangay_name
                FROM rabid_reports r
                LEFT JOIN admins verified_admin ON r.verifiedby_admin_id = verified_admin.id
                LEFT JOIN admins cancelled_admin ON r.cancelledby_admin_id = cancelled_admin.id
                LEFT JOIN barangays b ON r.barangay_id = b.id
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN barangays ub ON u.barangay_id = ub.id
                WHERE r.id = ?
            ");
            break;
            
        default:
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Invalid request type']));
    }

    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Request not found']));
    }

    // For user view, verify the request belongs to them
    if ($is_user_view && isset($request['user_id']) && $request['user_id'] != $_SESSION['user_id']) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Unauthorized access to this request']));
    }

    // Format phone numbers
    $user_phone_formatted = !empty($request['user_phone']) ? formatPhoneNumber($request['user_phone']) : '';
    $request_phone_formatted = !empty($request['phone']) ? formatPhoneNumber($request['phone']) : '';

    // Format user full name
    $user_full_name = htmlspecialchars(
        $request['user_first_name'] . ' ' . 
        (!empty($request['user_middle_name']) ? $request['user_middle_name'] . ' ' : '') . 
        $request['user_last_name']
    );

    // Format requester full name
    $requester_full_name = htmlspecialchars(
        $request['first_name'] . ' ' . 
        (!empty($request['middle_name']) ? $request['middle_name'] . ' ' : '') . 
        $request['last_name']
    );

    // Calculate ages
    $user_age = !empty($request['user_birthday']) ? calculateAge($request['user_birthday']) : 'N/A';
    $requester_age = !empty($request['birthday']) ? calculateAge($request['birthday']) : 'N/A';

    // Format offense count if available
    $offense_count_display = null;
    $fine_amount_display = null;
    
    if ($request_type === 'claim' && isset($request['offense_count']) && $request['offense_count'] !== null) {
        $offense_count_display = formatOffenseCount($request['offense_count']);
        $fine_amount_display = '₱' . number_format($request['fine_amount'], 2);
    }

    // Build the HTML response
    ob_start();
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
                    <?php if (!empty($request_phone_formatted)): ?>
                    <div class="mb-3">
                        <strong>Phone:</strong> <?= htmlspecialchars($request_phone_formatted) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['barangay_name'])): ?>
                    <div class="mb-3">
                        <strong>Barangay:</strong> <?= htmlspecialchars($request['barangay_name']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['complete_address'])): ?>
                    <div class="mb-3">
                        <strong>Address:</strong> <?= htmlspecialchars($request['complete_address']) ?>
                    </div>
                    <?php endif; ?>                    
                </div>
            </div>
            
            <?php if ($request_type === 'claim'): ?>
            <div class="row mt-3">
                <div class="col-12">
                   
                </div>
            </div>
            <?php elseif ($request_type === 'adoption'): ?>
            <div class="row mt-3">
                <div class="col-12">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Request Information Section -->
        <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
            <h4 class="border-bottom pb-2 mb-3">Request Information</h4>
            <div class="row">
                <!-- LEFT SIDE -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                    </div>
                    <?php if (isset($request['dog_status'])): ?>
                    <div class="mb-3">
                        <strong>Dog Status:</strong> <?= ucfirst(str_replace('_', ' ', $request['dog_status'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($request['dog_id'])): ?>
                    <div class="mb-3">
                        <strong>Dog ID:</strong> <?= htmlspecialchars($request['dog_id']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['name_of_dog'])): ?>
                    <div class="mb-3">
                        <strong>Dog Name:</strong> <?= htmlspecialchars($request['name_of_dog']) ?>
                    </div>
                    <?php if (!empty($request['remarks'])): ?>
<div class="mb-3">
    <strong>Remarks:</strong> <?= htmlspecialchars($request['remarks']) ?>
</div>
<?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($request['age_of_dog']) && $request['age_of_dog'] > 0): ?>
                    <div class="mb-3">
                        <strong>Dog Age:</strong> <?= $request['age_of_dog'] ?> years
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['adoption_reason'])): ?>
                    <div class="mb-3">
                        <strong>Adoption Reason:</strong> <?= htmlspecialchars($request['adoption_reason']) ?>
                    </div>
                    <?php if (!empty($request['remarks'])): ?>
<div class="mb-3">
    <strong>Remarks:</strong> <?= htmlspecialchars($request['remarks']) ?>
</div>
<?php endif; ?>
                    <?php endif; ?>
                    <div class="mb-3">
                        <strong>Submitted:</strong> <?= date('F d, Y h:i A', strtotime($request['created_at'])) ?>
                    </div>
                </div>

                <!-- RIGHT SIDE -->
                <div class="col-md-6">
                    <?php if ($request['status'] == 'approved' && !empty($request['approved_admin_name'])): ?>
                        <div class="mb-3"><strong>Approved by:</strong> <?= htmlspecialchars($request['approved_admin_name']) ?></div>
                        <div class="mb-3"><strong>Approved Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                    <?php endif; ?>

                    <?php if ($request['status'] == 'completed' && !empty($request['completed_admin_name'])): ?>
                        <div class="mb-3"><strong>Completed by:</strong> <?= htmlspecialchars($request['completed_admin_name']) ?></div>
                        <div class="mb-3"><strong>Completed Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                    <?php endif; ?>

                    <?php if ($request['status'] == 'declined' && !empty($request['declined_admin_name'])): ?>
                        <div class="mb-3"><strong>Declined by:</strong> <?= htmlspecialchars($request['declined_admin_name']) ?></div>
                        <div class="mb-3"><strong>Declined Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                    <?php endif; ?>

                    <?php if ($request['status'] == 'verified' && !empty($request['verified_admin_name'])): ?>
                        <div class="mb-3"><strong>Verified by:</strong> <?= htmlspecialchars($request['verified_admin_name']) ?></div>
                        <div class="mb-3"><strong>Verified Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                    <?php endif; ?>

                    <?php if ($request['status'] == 'cancelled'): ?>
                        <div class="mb-3"><strong>Cancelled by:</strong> 
                            <?= !empty($request['cancelled_admin_name']) 
                                ? htmlspecialchars($request['cancelled_admin_name']) 
                                : 'User' ?>
                        </div>
                        <div class="mb-3"><strong>Cancelled Date:</strong> <?= date('F d, Y h:i A', strtotime($request['updated_at'])) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($request['reason'])): ?>
                        <div class="mb-3"><strong>Reason:</strong> <?= htmlspecialchars($request['reason']) ?></div>
                    <?php endif; ?>
                    
                    <!-- Offense Count and Fine Amount - Only for claims with stored values -->
                    <?php if ($offense_count_display && $fine_amount_display): ?>
                        <div class="mb-3"><strong>Offense Count:</strong> <?= $offense_count_display ?></div>
                        <div class="mb-3"><strong>Fine Amount:</strong> <?= $fine_amount_display ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Images Section -->
        <?php if (($request_type === 'claim' || $request_type === 'adoption') && !empty($request['dog_image'])): ?>
        <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
            <h4 class="border-bottom pb-2 mb-3">Dog Image</h4>
            <div class="text-center">
                <img src="<?= '/' . htmlspecialchars($request['dog_image']) ?>" 
                     class="img-fluid rounded document-view" 
                     style="max-height: 300px; cursor: pointer;"
                     data-src="<?= '/' . htmlspecialchars($request['dog_image']) ?>"
                     data-title="Dog Image">
            </div>
        </div>
        <?php endif; ?>

        <?php if ($request_type === 'report' && !empty($request['proof_path'])): ?>
        <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
            <h4 class="border-bottom pb-2 mb-3">Report Proof</h4>
            <div class="text-center">
                <img src="<?= '/' . htmlspecialchars($request['proof_path']) ?>" 
                     class="img-fluid rounded document-view" 
                     style="max-height: 300px; cursor: pointer;"
                     data-src="<?= '/' . htmlspecialchars($request['proof_path']) ?>"
                     data-title="Report Proof">
            </div>
        </div>
        <?php endif; ?>

        <?php if (($request_type === 'claim') && !empty($request['receipt_photo_path'])): ?>
        <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
            <h4 class="border-bottom pb-2 mb-3">Receipt Photo</h4>
            <div class="text-center">
                <img src="<?= '/' . htmlspecialchars($request['receipt_photo_path']) ?>" 
                     class="img-fluid rounded document-view" 
                     style="max-height: 300px; cursor: pointer;"
                     data-src="<?= '/' . htmlspecialchars($request['receipt_photo_path']) ?>"
                     data-title="Receipt Photo">
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (($request_type === 'claim' || $request_type === 'adoption') && !empty($request['handover_photo_path'])): ?>
        <div class="info-section bg-white p-4 mb-4 rounded shadow-sm">
            <h4 class="border-bottom pb-2 mb-3">Handover Photo</h4>
            <div class="text-center">
                <img src="<?= '/' . htmlspecialchars($request['handover_photo_path']) ?>" 
                     class="img-fluid rounded document-view" 
                     style="max-height: 300px; cursor: pointer;"
                     data-src="<?= '/' . htmlspecialchars($request['handover_photo_path']) ?>"
                     data-title="Handover Photo">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Fullscreen Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="imageModalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal', aria-label="Close"></button>
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
    <?php
    $html = ob_get_clean();
    echo $html;

} catch (Exception $e) {
    header('Content-Type: application/json');
    die(json_encode(['error' => $e->getMessage()]));
}
?>