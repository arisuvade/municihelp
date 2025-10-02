<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT name, middle_name, last_name, birthday, address, barangay_id FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Format birthday to YYYY-MM-DD for date input
$formatted_birthday = '';
if (!empty($user_data['birthday'])) {
    $formatted_birthday = date('Y-m-d', strtotime($user_data['birthday']));
}

// Get barangay name from barangay_id
$barangay_name = '';
if (!empty($user_data['barangay_id'])) {
    $barangay_query = $conn->prepare("SELECT name FROM barangays WHERE id = ?");
    $barangay_query->bind_param("i", $user_data['barangay_id']);
    $barangay_query->execute();
    $barangay_result = $barangay_query->get_result();
    if ($barangay_row = $barangay_result->fetch_assoc()) {
        $barangay_name = $barangay_row['name'];
    }
}

$pageTitle = "MSWD - Form";
include '../../includes/header.php';

// Get all assistance types (parent programs)
$all_assistance_types = $conn->query("SELECT * FROM mswd_types WHERE parent_id IS NULL")->fetch_all(MYSQLI_ASSOC);

// Map program id => program
$programMap = [];
foreach ($all_assistance_types as $type) {
    $programMap[$type['id']] = $type;
}

$sorted_online = [];
$sorted_walkin = [];

// Sort programs based on new structure
foreach ($programMap as $id => $program) {
    // Online programs (IDs 1-16)
    if ($id >= 1 && $id <= 16) {
        $sorted_online[] = $program;
    } 
    // Walk-in programs (IDs 17-32)
    elseif ($id > 16) {
        $sorted_walkin[] = $program;
    }
}

// Fetch sub-programs (children)
$sub_programs = $conn->query("SELECT * FROM mswd_types WHERE parent_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);

// Fetch requirements
$mswd_requirements = $conn->query("SELECT * FROM mswd_types_requirements")->fetch_all(MYSQLI_ASSOC);

// Fetch barangays
$barangays = $conn->query("SELECT * FROM barangays ORDER by name")->fetch_all(MYSQLI_ASSOC);

// Get current Sulong Dulong beneficiary count - ONLY ACTIVE
$sulongDulongCount = $conn->query("SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries WHERE status = 'Active'")->fetch_assoc()['total'];
$maxBeneficiaries = 800;
$sulongDulongLeft = $maxBeneficiaries - $sulongDulongCount;

// Get equipment availability
$equipment_availability = $conn->query("
    SELECT t.id, t.name, ei.available_quantity 
    FROM mswd_types t 
    LEFT JOIN equipment_inventory ei ON t.id = ei.equipment_type_id 
    WHERE t.parent_id = 8
")->fetch_all(MYSQLI_ASSOC);

// Helper to check if program has children
function has_children($type_id, $sub_programs) {
    foreach ($sub_programs as $sub) {
        if ($sub['parent_id'] == $type_id) return true;
    }
    return false;
}
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
        
        .request-form-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .request-form-card .card-header {
            text-align: center;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .btn-submit {
            background-color: var(--munici-green);
            color: white;
            padding: 12px 25px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            border-radius: 8px;
        }
        
        .btn-submit:hover {
            background-color: #0E3B85;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-submit:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .required-field::after {
            content: " *";
            color: red;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .file-upload-container {
            border: 2px dashed #ddd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        
        .file-upload-container:hover {
            border-color: var(--munici-green);
        }
        
        .file-upload-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            margin: 10px auto 0;
            display: none;
        }
        
        .program-group-title {
            font-weight: bold;
            color: var(--munici-green);
            padding: 5px 10px;
            background-color: #f0f0f0;
            margin-top: 10px;
            border-radius: 5px;
        }
        
        .offline-notice {
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Counter styles */
        .counter {
            font-size: 1.1rem;
            padding: 10px 15px;
            border-left: 4px solid #6c757d;
            background-color: #f8f9fa;
            margin-top: 1rem;
        }
        
        .counter-text {
            font-weight: bold;
            color: #4361ee;
        }
        
        .counter-full {
            color: #dc3545;
        }
        
        /* Availability table styles */
        .availability-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .availability-table th {
            background-color: var(--munici-green);
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        .availability-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .availability-table tr:last-child td {
            border-bottom: none;
        }
        
        .availability-table tr:hover {
            background-color: #f8f9fa;
        }

        /* Add this to your existing CSS */
select option:disabled {
    color: #999 !important;
    background-color: #f5f5f5 !important;
    cursor: not-allowed;
}

/* Style for out of stock items in the availability table */
.text-danger {
    color: #dc3545 !important;
    font-weight: bold;
}

.disabled-field {
    background-color: #f8f9fa;
    opacity: 1;
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
            
            .dashboard-header p {
                font-size: 0.9rem;
            }
            
            .form-section h5 {
                font-size: 1.1rem;
            }
            
            .btn-submit {
                width: 100%;
                padding: 12px;
            }
            
            .row.g-2 > [class^="col-"] {
                margin-bottom: 1rem;
            }
            
            .availability-table {
                font-size: 0.9rem;
            }
            
            .availability-table th, 
            .availability-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">MSWD Request</h1>
                    <p class="mb-0">Fill out the form to request assistance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="request-form-card card">
            <div class="card-header">
                MSWD Request Form
            </div>
            <div class="card-body">
                <div class="alert-warning">
                    <strong>Paunawa:</strong> Ang aming sistema ay nakabatay lamang sa mga dokumentong inyong isinusumite. Hindi kami mananagot kung sakaling may hindi pagkakatugma sa inyong ipinasa online at sa physical na dokumento. Responsibilidad ng nagpapasa na tiyakin ang tamang impormasyon at pagkakapareho ng mga dokumento. Kung may mali o hindi tugmang dokumento, maaaring hindi kayo makatangap ng assistance. Salamat sa inyong pang-unawa.
                </div>
                
                <!-- Initial Counters Section (disappears when program is selected) -->
                <div id="initialCountersSection">
                    <!-- Sulong Dulong Availability -->
                    <div class="form-section">
                        <h5 class="mb-4">Sulong Dunong Beneficiaries</h5>
                        <table class="availability-table">
                            <colgroup>
                                <col style="width:50%">
                                <col style="width:50%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Limit</th>
                                    <th>Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= $maxBeneficiaries ?></td>
                                    <td><?= $sulongDulongLeft ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Equipment Availability -->
                    <div class="form-section">
    <h5 class="mb-4">Equipment Availability</h5>
    <table class="availability-table">
        <colgroup>
            <col style="width:50%">
            <col style="width:50%">
        </colgroup>
        <thead>
            <tr>
                <th>Equipment</th>
                <th>Available</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($equipment_availability as $equipment): ?>
                <?php 
                $available = $equipment['available_quantity'] ?? 0;
                $isAvailable = $available > 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($equipment['name']) ?></td>
                    <td class="<?= $isAvailable ? 'text-success' : 'text-danger' ?>">
                        <?= $available ?>
                        <?= !$isAvailable ? ' (Out of Stock)' : '' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
                </div>
                
                <!-- Program Selection Form -->
                <form id="programSelectionForm">
                    <div class="form-section">
                        <h5 class="mb-4">Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <select class="form-select" id="program" name="assistance_id" required>
                                    <option value="" selected disabled>Select a program</option>
                                    
                                    <optgroup label="Online Programs">
                                    <?php foreach ($sorted_online as $type): ?>
                                        <option value="<?= $type['id'] ?>" 
                                                data-is-online="<?= $type['is_online'] ?>"
                                                data-has-children="<?= has_children($type['id'], $sub_programs) ?>">
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                    
                                    <optgroup label="Walk-in Programs">
                                    <?php foreach ($sorted_walkin as $type): ?>
                                        <option value="<?= $type['id'] ?>" 
                                                data-is-online="<?= $type['is_online'] ?>"
                                                data-has-children="<?= has_children($type['id'], $sub_programs) ?>">
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Program Selection -->
                    <div class="form-section" id="subProgramSection" style="display: none;">
                        <h5 class="mb-4">Sub-Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <select class="form-select" id="subProgram" name="sub_program_id">
                                    <option value="" selected disabled>Select a sub-program</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Others input field -->
                    <div class="form-section" id="othersInputSection" style="display: none;">
                        <h5 class="mb-4">Specify Assistance Needed</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <input type="text" class="form-control" id="othersInput" name="assistance_name" placeholder="Please specify the assistance you need">
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Online Form Section -->
                <div id="onlineFormSection" style="display: none;">
                    <form id="requestForm" action="submit_request.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
                        <input type="hidden" name="assistance_id" id="formAssistanceId">
                        <input type="hidden" name="sub_program_id" id="formSubProgramId">
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h5 class="mb-4">Personal Information of the Applicant</h5>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                                    <input type="hidden" name="first_name" value="<?= htmlspecialchars($user_data['name'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?= htmlspecialchars($user_data['middle_name'] ?? '') ?>">
                                    <input type="hidden" name="middle_name" value="<?= htmlspecialchars($user_data['middle_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>" required>
                                    <input type="hidden" name="last_name" value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="birthday" class="form-label required-field">Birthday</label>
                                    <input type="date" class="form-control" id="birthday" name="birthday" 
                                           value="<?= htmlspecialchars($formatted_birthday) ?>" required>
                                    <input type="hidden" name="birthday" value="<?= htmlspecialchars($user_data['birthday'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="relation" class="form-label required-field">Relation</label>
                                    <select class="form-select" id="relation" name="relation" required>
                                        <option value="self" selected>Self (Ikaw)</option>
                                        <option value="others">Others</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="barangay" class="form-label required-field">Barangay</label>
                                    <select class="form-select" id="barangay" name="barangay_id" required>
                                        <option value="" disabled>Select barangay</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?= $barangay['id'] ?>" 
                                                <?= ($barangay['id'] == $user_data['barangay_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($barangay['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="barangay_id" value="<?= $user_data['barangay_id'] ?? '' ?>">
                                </div>
                                <div class="col-md-8">
                                    <label for="complete_address" class="form-label required-field">Complete Address</label>
                                    <input type="text" class="form-control" id="complete_address" name="complete_address" 
                                           value="<?= htmlspecialchars($user_data['address'] ?? '') ?>" required>
                                    <input type="hidden" name="complete_address" value="<?= htmlspecialchars($user_data['address'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Requirements Section -->
                        <div class="form-section">
                            <h5 class="mb-4">Required Documents</h5>
                            <div class="row g-2" id="requirementsContainer">
                                <!-- This will be populated by JavaScript -->
                            </div>
                        </div>
                        <div class="form-section">
                            <h5 class="mb-4">Additional Information</h5>
                            <div class="row g-2">
                                <div class="col-md-12">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                            placeholder="Please specify (e.g., medicine name, type of equipment, or other request)"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-submit" id="submitButton">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                        </div>
                    </form>
                    
                    <!-- Dynamic Counters (shown when specific program is selected) -->
                    <div id="sulongDulongCounter" class="counter" style="display: none;">
                        <strong>Available:</strong> 
                        <span id="sulongDulongAvailable"><?= $sulongDulongLeft ?></span>
                    </div>
                    
                    <div id="equipmentCounter" class="counter" style="display: none;">
                        <strong>Available:</strong> 
                        <span id="currentEquipmentCount">0</span>
                    </div>
                </div>

                <!-- Walk-in Information Section -->
                <div id="walkinInfoSection" style="display: none;">
                    <div class="form-section">
                        <h5 class="mb-4">Walk-in Procedure</h5>
                        <div class="alert">
                            <p>Ang programang ito ay kailangang asikasuhin nang personal. Narito ang mga kailangang dalhin at proseso:</p>
                            <ul id="walkinRequirementsList"></ul>
                            <!-- Add this inside the walk-in requirements section -->
<div id="soloParentDownloadWalkin" style="display: none; margin-top: 15px;">
    <p class="mb-2">Paki download ang affidavit of solo parent dahil kasama ito sa requirements. 
        <a href="../../../assets/pdf/Sample_AFFIDAVIT_for_Solo_Parents.pdf" class="text-primary" download>
            (Download Sample)
        </a>
    </p>
</div>
                            <p class="mt-3"><strong>Lokasyon ng Opisina:</strong> Municipal Hall, Pulilan, Bulacan</p>
                            <p><strong>Oras ng Opisina:</strong> Lunes hanggang Biyernes, 8:00 AM hanggang 5:00 PM</p>
                            
                            <!-- Updated Contact Information Section -->
                            <div class="contact-options mt-3">
                                <p><strong>Para sa mga katanungan:</strong></p>
                                <ul>
                                    <li>Maaaring <a href="../../user/inquiry.php" class="text-primary">magsumite ng inquiry</a> online (piliin ang angkop na department)</li>
                                    <li>Magpunta sa opisina para sa direktang asistensya</li>
                                </ul>
                            </div>
                        </div>
                        <div class="offline-notice">
                            <strong>Paunawa:</strong> Ang pagkuha ng programang ito ay hindi po maaaring gawin online. Kailangan po itong personal na asikasuhin. Salamat po sa inyong pang-unawa.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h4 class="mb-3">Request Submitted Successfully!</h4>
                    <p class="mb-4">Your request has been received. You will be notified about the status of your application.</p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="location.href='status.php'">
                        View Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
    // Store original user data for resetting
    const originalUserData = {
        firstName: "<?= addslashes($user_data['name'] ?? '') ?>",
        middleName: "<?= addslashes($user_data['middle_name'] ?? '') ?>",
        lastName: "<?= addslashes($user_data['last_name'] ?? '') ?>",
        birthday: "<?= addslashes($formatted_birthday) ?>",
        barangayId: "<?= addslashes($user_data['barangay_id'] ?? '') ?>",
        address: "<?= addslashes($user_data['address'] ?? '') ?>"
    };

    const subPrograms = <?php echo json_encode($sub_programs); ?>;
    const requirements = <?php echo json_encode($mswd_requirements); ?>;
    const maxBeneficiaries = <?= $maxBeneficiaries ?>;
    const equipmentAvailability = <?php echo json_encode($equipment_availability); ?>;
    
    let selectedProgramType = null;
    let isLimitReached = false;
    let isEquipmentAvailable = true;

    // Function to gray out unavailable equipment options
    function grayOutUnavailableEquipment() {
        // Gray out main program options for unavailable equipment
        $('#program option').each(function() {
            const optionValue = $(this).val();
            const equipment = equipmentAvailability.find(e => e.id == optionValue);
            
            if (equipment) {
                const available = equipment.available_quantity || 0;
                $(this).prop('disabled', available <= 0);
                
                if (available <= 0) {
                    $(this).text($(this).text() + ' (Out of Stock)');
                }
            }
        });
        
        // Gray out sub-program options for unavailable equipment
        $('#subProgram option').each(function() {
            const optionValue = $(this).val();
            const equipment = equipmentAvailability.find(e => e.id == optionValue);
            
            if (equipment) {
                const available = equipment.available_quantity || 0;
                $(this).prop('disabled', available <= 0);
                
                if (available <= 0) {
                    $(this).text($(this).text() + ' (Out of Stock)');
                }
            }
        });
    }

    // Handle relation change to show/hide additional fields
$('#relation').change(function() {
    const relation = $(this).val();
    
    if (relation === 'self') {
        // Use user's information and disable fields
        $('#first_name').val(originalUserData.firstName).prop('disabled', true).addClass('disabled-field');
        $('#middle_name').val(originalUserData.middleName).prop('disabled', true).addClass('disabled-field');
        $('#last_name').val(originalUserData.lastName).prop('disabled', true).addClass('disabled-field');
        $('#birthday').val(originalUserData.birthday).prop('disabled', true).addClass('disabled-field');
        $('#barangay').val(originalUserData.barangayId).prop('disabled', true).addClass('disabled-field');
        $('#complete_address').val(originalUserData.address).prop('disabled', true).addClass('disabled-field');
        
        // Update hidden fields with user data
        $('input[name="first_name"]').val(originalUserData.firstName);
        $('input[name="middle_name"]').val(originalUserData.middleName);
        $('input[name="last_name"]').val(originalUserData.lastName);
        $('input[name="birthday"]').val("<?= $user_data['birthday'] ?? '' ?>");
        $('input[name="barangay_id"]').val(originalUserData.barangayId);
        $('input[name="complete_address"]').val(originalUserData.address);
    } else {
        // Enable fields for editing and clear them
        $('#first_name').prop('disabled', false).removeClass('disabled-field').val('');
        $('#middle_name').prop('disabled', false).removeClass('disabled-field').val('');
        $('#last_name').prop('disabled', false).removeClass('disabled-field').val('');
        $('#birthday').prop('disabled', false).removeClass('disabled-field').val('');
        $('#barangay').prop('disabled', false).removeClass('disabled-field').val('');
        $('#complete_address').prop('disabled', false).removeClass('disabled-field').val('');
        
        // Clear hidden fields
        $('input[name="first_name"]').val('');
        $('input[name="middle_name"]').val('');
        $('input[name="last_name"]').val('');
        $('input[name="birthday"]').val('');
        $('input[name="barangay_id"]').val('');
        $('input[name="complete_address"]').val('');
    }
});

    // Initialize the form with Self relation selected
    $('#relation').trigger('change');

    // Call this function on page load
    grayOutUnavailableEquipment();

    // Program selection change handler
    $('#program').change(function() {
        const selectedOption = $(this).find('option:selected');
        const selectedValue = selectedOption.val();
        
        // Hide initial counters when any program is selected
        $('#initialCountersSection').hide();
        
        if (!selectedValue) return;
        
        selectedProgramType = {
            id: selectedValue,
            isOnline: selectedOption.data('is-online'),
            hasChildren: selectedOption.data('has-children'),
            isOthers: selectedValue == 16 // "Others" is ID 16
        };

        // Update hidden form fields
        $('#formAssistanceId').val(selectedValue);
        $('#formSubProgramId').val('');

        // Reset and hide sections
        $('#subProgramSection').hide();
        $('#onlineFormSection').hide();
        $('#walkinInfoSection').hide();
        $('#othersInputSection').hide();
        $('#sulongDulongCounter').hide();
        $('#equipmentCounter').hide();

        // Show/hide solo parent download link
        toggleSoloParentDownload(selectedValue);

        // Update counters
        updateSulongDulongCounter();
        updateEquipmentCounter();

        // Show others input field if "Others" is selected
        if (selectedProgramType.isOthers) {
            $('#othersInputSection').show();
            // Show the form for "Others"
            $('#onlineFormSection').show();
            loadRequirements(16); // Load requirements for Others (ID 16)
            return;
        }

        // Handle programs with children
        if (selectedProgramType.hasChildren && !selectedProgramType.isOthers) {
            $('#subProgramSection').show();
            $('#subProgram').empty().append('<option value="" selected disabled>Select a sub-program</option>');
            
            // Filter and populate sub-programs
            const filteredSubs = subPrograms.filter(sub => sub.parent_id == selectedProgramType.id);
            filteredSubs.forEach(sub => {
                $('#subProgram').append(`<option value="${sub.id}" data-is-online="${sub.is_online}">${sub.name}</option>`);
            });
            
            // Gray out unavailable equipment in sub-program dropdown
            setTimeout(grayOutUnavailableEquipment, 100);
        } else if (selectedValue && !selectedProgramType.isOthers) {
            // If no children and a program is selected, show appropriate section
            showAppropriateSection(selectedProgramType);
        }
    });

    // Sub-program selection change handler
    $('#subProgram').change(function() {
        const selectedOption = $(this).find('option:selected');
        if (selectedOption.val()) {
            selectedProgramType = {
                id: selectedOption.val(),
                isOnline: selectedOption.data('is-online'),
                hasChildren: false,
                isOthers: false
            };
            // Update hidden form field
            $('#formSubProgramId').val(selectedOption.val());
            
            // Show/hide solo parent download link
            toggleSoloParentDownload(selectedOption.val());
            
            showAppropriateSection(selectedProgramType);
            updateSulongDulongCounter();
            updateEquipmentCounter();
        } else {
            // If sub-program is deselected, hide all sections
            $('#onlineFormSection').hide();
            $('#walkinInfoSection').hide();
            $('#sulongDulongCounter').hide();
            $('#equipmentCounter').hide();
            
            // Hide solo parent download link
            $('#soloParentDownloadWalkin').hide();
            $('#soloParentDownloadOnline').hide();
        }
    });

    // Function to toggle solo parent download section
    function toggleSoloParentDownload(programId) {
        const soloParentIds = [19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32];
        const isSoloParent = soloParentIds.includes(parseInt(programId));
        
        // Only show the download section for online forms, not walk-in
        $('#soloParentDownloadOnline').toggle(isSoloParent);
    }

    // Function to update Sulong Dulong counter
    function updateSulongDulongCounter() {
        const assistanceId = $('#subProgram').val() || $('#program').val();
        const isSulongDulong = [33, 34, 35].includes(parseInt(assistanceId));
        
        if (isSulongDulong) {
            // Fetch current count via AJAX
            $.ajax({
                url: 'get_beneficiary_count.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const available = maxBeneficiaries - response.count;
                        $('#sulongDulongAvailable').text(available);
                        $('#sulongDulongCounter').show();
                        
                        // Disable submit button if limit reached
                        isLimitReached = response.count >= maxBeneficiaries;
                        $('#submitButton').prop('disabled', isLimitReached);
                        
                        if (isLimitReached) {
                            $('#sulongDulongCounter').addClass('counter-full');
                        } else {
                            $('#sulongDulongCounter').removeClass('counter-full');
                        }
                    }
                },
                error: function() {
                    console.error('Failed to update beneficiary count');
                    $('#sulongDulongCounter').hide();
                }
            });
        } else {
            $('#sulongDulongCounter').hide();
            $('#submitButton').prop('disabled', false);
        }
    }

    // Function to update equipment counter
    function updateEquipmentCounter() {
        const assistanceId = $('#subProgram').val() || $('#program').val();
const isEquipment = [9, 10, 11, 12].includes(parseInt(assistanceId)) || parseInt(assistanceId) >= 35;
        
        if (isEquipment) {
            $('#equipmentCounter').show();
            
            // Find the selected equipment
            const selectedEquipment = equipmentAvailability.find(e => e.id == assistanceId);
            const available = selectedEquipment ? (selectedEquipment.available_quantity || 0) : 0;
            
            $('#currentEquipmentCount').text(available);
            
            // Disable submit button if no equipment available
            isEquipmentAvailable = available > 0;
            $('#submitButton').prop('disabled', !isEquipmentAvailable);
            
            if (!isEquipmentAvailable) {
                $('#equipmentCounter').addClass('counter-full');
            } else {
                $('#equipmentCounter').removeClass('counter-full');
            }
        } else {
            $('#equipmentCounter').hide();
            $('#submitButton').prop('disabled', false);
        }
    }

    // Function to show the appropriate section based on program type
    function showAppropriateSection(programType) {
        if (programType.isOnline == 1) {
            // Show online form
            $('#onlineFormSection').show();
            $('#walkinInfoSection').hide();
            loadRequirements(programType.id);
        } else {
            // Show walk-in information
            $('#walkinInfoSection').show();
            $('#onlineFormSection').hide();
            loadWalkinRequirements(programType.id);
        }
    }

    // Function to load requirements for online form
    function loadRequirements(typeId) {
        const filteredRequirements = requirements.filter(req => req.mswd_types_id == typeId);
        const container = $('#requirementsContainer');
        container.empty();
        
        if (filteredRequirements.length === 0) {
            container.append('<div class="col-12"><p>No requirements found for this program.</p></div>');
            return;
        }
        
        // Add all requirements in a 2-column layout
        filteredRequirements.forEach((req, index) => {
            const requirementHtml = `
                <div class="col-md-6">
                    <div class="file-upload-container">
                        <label for="requirement_${req.id}" class="file-upload-label required-field">${req.name}</label>
                        <p class="text-muted">Please upload the required document</p>
                        <input type="file" id="requirement_${req.id}" name="requirements[${req.id}]" 
                               accept="image/*,.pdf" class="form-control" required>
                        <img id="requirement_${req.id}_preview" class="file-preview" src="#" alt="Preview">
                    </div>
                </div>
            `;
            container.append(requirementHtml);
            
            // Add change handler for file input preview
            $(`#requirement_${req.id}`).change(function() {
                readURL(this, `#requirement_${req.id}_preview`);
            });
        });
        
        // Add download section only for Solo Parent programs at the end
        const soloParentIds = [19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32];
        if (soloParentIds.includes(parseInt(typeId))) {
            container.append(`
                <div class="col-12">
                    <div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; border-left: 4px solid #2C80C6;">
                        <p class="mb-2"><strong>Paalala:</strong> Paki download ang affidavit of solo parent dahil kasama ito sa requirements.</p>
                        <a href="../../../assets/pdf/Sample_AFFIDAVIT_for_Solo_Parents.pdf" class="btn btn-primary btn-sm" download>
                            <i class="fas fa-download me-2"></i> Download Sample
                        </a>
                    </div>
                </div>
            `);
        }
    }

    // Function to read file and display preview
    function readURL(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                $(previewId).attr('src', e.target.result).show();
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Function to load requirements for walk-in info
    function loadWalkinRequirements(typeId) {
        const filteredRequirements = requirements.filter(req => req.mswd_types_id == typeId);
        const container = $('#walkinRequirementsList');
        container.empty();
        
        if (filteredRequirements.length === 0) {
            container.append('<li>Walang partikular na nakalista na mga kinakailangan</li>');
            return;
        }
        
        // Add all requirements as list items
        filteredRequirements.forEach(req => {
            container.append(`<li>${req.name}</li>`);
        });
        
        // Add download link only for Solo Parent programs at the end of the list
        const soloParentIds = [19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32];
        if (soloParentIds.includes(parseInt(typeId))) {
            container.append(`
                <li>
                    Paki download ang affidavit of solo parent dahil kasama ito sa requirements.
                    <a href="../../../assets/pdf/Sample_AFFIDAVIT_for_Solo_Parents.pdf" 
                       class="text-primary" 
                       target="_blank"
                       download="Sample_AFFIDAVIT_for_Solo_Parents.pdf">
                        (Download Sample)
                    </a>
                </li>
            `);
        }
    }

    // Form submission handler
    $('#requestForm').submit(async function(e) {
    e.preventDefault();
    
    // If limit reached, prevent submission
    if (isLimitReached || !isEquipmentAvailable) {
        Swal.fire('Error', isLimitReached 
            ? 'Maximum number of beneficiaries reached (800). Cannot submit new requests.' 
            : 'No equipment available. Cannot submit new requests.', 'error');
        return;
    }

    // Clear previous errors
    $('.is-invalid').removeClass('is-invalid');
    $('.text-danger').text('');
    $('.file-upload-container').css('border-color', '#ddd');

    // Update hidden fields with current visible values before submission
    const relation = $('#relation').val();
    if (relation === 'others') {
        // For "Others", use the values from the visible fields
        $('input[name="first_name"]').val($('#first_name').val());
        $('input[name="middle_name"]').val($('#middle_name').val());
        $('input[name="last_name"]').val($('#last_name').val());
        $('input[name="birthday"]').val($('#birthday').val());
        $('input[name="barangay_id"]').val($('#barangay').val());
        $('input[name="complete_address"]').val($('#complete_address').val());
    }
    // For "Self", the hidden fields already contain the correct values

    // Validate required fields
    let isValid = true;
    $(this).find('[required]').each(function() {
        if (!$(this).val()) {
            isValid = false;
            $(this).addClass('is-invalid');
            if ($(this).is('input[type="file"]')) {
                $(this).closest('.file-upload-container').css('border-color', 'red');
            }
        }
    });

    if (!isValid) {
        Swal.fire('Error', 'Please fill all required fields', 'error');
        return;
    }

    // If Others is selected, validate the assistance_name field
    if ($('#program').val() == 16 && !$('#othersInput').val()) {
        Swal.fire('Error', 'Please specify the assistance you need', 'error');
        $('#othersInput').addClass('is-invalid');
        return;
    }

    // Validate file sizes
    let filesValid = true;
    $('input[type="file"][required]').each(function() {
        if (!this.files || this.files.length === 0) {
            filesValid = false;
            $(this).addClass('is-invalid');
            $(this).closest('.file-upload-container').css('border-color', 'red');
        } else if (this.files[0].size > 5 * 1024 * 1024) {
            filesValid = false;
            $(this).addClass('is-invalid');
            // Show error in file upload container
            $(this).closest('.file-upload-container').css('border-color', 'red')
                .find('.text-muted').addClass('text-danger').text('File too large (max 5MB)');
        }
    });

    if (!filesValid) {
        Swal.fire('Error', 'Please check your file uploads', 'error');
        return;
    }

    // Prepare check data - use values from hidden fields
    const checkData = {
        first_name: $('input[name="first_name"]').val(),
        last_name: $('input[name="last_name"]').val(),
        middle_name: $('input[name="middle_name"]').val(),
        barangay_id: $('input[name="barangay_id"]').val(),
        birthday: $('input[name="birthday"]').val(),
        assistance_id: $('#subProgram').val() || $('#program').val()
    };

    // Disable submit button
    const submitBtn = $('button[type="submit"]');
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

    try {
        // Check for existing request
        const checkResponse = await $.ajax({
            url: 'check_existing_request.php',
            type: 'POST',
            data: checkData,
            dataType: 'json'
        });

        if (checkResponse.error) {
            throw new Error(checkResponse.error);
        }

        if (checkResponse.exists) {
            let alertOptions = {
                title: 'Existing Request Found',
                text: checkResponse.message,
                icon: 'warning',
                confirmButtonText: 'OK'
            };

            if (checkResponse.is_sulong_dulong_beneficiary) {
                alertOptions.title = 'Already a Beneficiary';
                alertOptions.icon = 'error';
            }

            if (checkResponse.is_blocked) {
                alertOptions.title = 'Blocked';
                alertOptions.icon = 'error';
            }

            await Swal.fire(alertOptions);
            return;
        }

        // Prepare form data - use the form directly which will include hidden fields
        const formData = new FormData(this);
        if ($('#program').val() == 16) {
            formData.append('assistance_name', $('#othersInput').val());
        }

        // Submit the form
        const submitResponse = await $.ajax({
            url: 'submit_request.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        });

        if (submitResponse.success) {
            $('#successModal').modal('show');
        } else {
            throw new Error(submitResponse.message || 'Submission failed');
        }
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: error.message || 'An error occurred. Please try again.',
            icon: 'error'
        });
    } finally {
        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
    }
});
});
    </script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>