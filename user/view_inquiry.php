<?php
session_start();
require_once __DIR__ . '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['error' => 'Invalid inquiry ID']));
}

// Function to calculate age from birthday
function calculateAge($birthday) {
    if (empty($birthday)) return 'N/A';
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age . ' years old';
}

// Function to format phone number
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

$inquiry_id = (int)$_GET['id'];

// Get user information along with the inquiry
$query = "
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
    WHERE i.id = ? AND i.user_id = ?
";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die(json_encode(['error' => 'Database error: ' . $conn->error]));
}

$stmt->bind_param("ii", $inquiry_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$inquiry = $result->fetch_assoc();

if (!$inquiry) {
    die('<div class="alert alert-danger">Inquiry not found or you don\'t have permission to view it</div>');
}

// Format user full name
$user_full_name = htmlspecialchars(
    $inquiry['user_first_name'] . ' ' . 
    (!empty($inquiry['user_middle_name']) ? $inquiry['user_middle_name'] . ' ' : '') . 
    $inquiry['user_last_name']
);

// Calculate age
$user_age = calculateAge($inquiry['user_birthday']);

// Format phone number
$user_phone_formatted = !empty($inquiry['user_phone']) ? formatPhoneNumber($inquiry['user_phone']) : '';
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
                    <h6 class="mb-0">Your Question</h6>
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
                    <h6 class="mb-0">Answer</h6>
                </div>
                <div class="document-body p-3">
                    <p><?= nl2br(htmlspecialchars($inquiry['answer'])) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>