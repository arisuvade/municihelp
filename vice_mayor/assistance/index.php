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

$pageTitle = "Assistance - Form";
include '../../includes/header.php';

// Get main assistance types (parent programs)
$assistance_types = $conn->query("SELECT * FROM assistance_types WHERE parent_id IS NULL ORDER BY 
    CASE 
        WHEN name = 'Medical Assistance' THEN 1
        WHEN name = 'Nebulizer Request' THEN 2
        WHEN name = 'Glucometer Request' THEN 3
        WHEN name = 'Wheelchair Request' THEN 4
        WHEN name = 'Laboratory Assistance' THEN 5
        WHEN name = 'Burial Assistance' THEN 6
        WHEN name = 'Educational Assistance' THEN 7
        ELSE 8
    END")->fetch_all(MYSQLI_ASSOC);

// Get sub-programs
$sub_programs = $conn->query("SELECT * FROM assistance_types WHERE parent_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);

$barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get family relations
$family_relations = $conn->query("SELECT * FROM family_relations ORDER BY id")->fetch_all(MYSQLI_ASSOC);
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
        }
        
        .disabled-field {
            background-color: #f8f9fa;
            opacity: 1;
        }
        
        .readonly-input {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Assistance Request</h1>
                    <p class="mb-0">Fill out the form to request assistance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="request-form-card card">
            <div class="card-header">
                Assistance Request Form
            </div>
            <div class="card-body">
                <div class="alert-warning">
                    <strong>Paunawa:</strong> Ang aming sistema ay nakabatay lamang sa mga dokumentong inyong isinusumite. Hindi kami mananagot kung sakaling may hindi pagkakatugma sa inyong ipinasa online at sa physical na dokumento. Responsibilidad ng nagpapasa na tiyakin ang tamang impormasyon at pagkakapareho ng mga dokumento. Kung may mali o hindi tugmang dokumento, maaaring hindi kayo makatangap ng assistance. Salamat sa inyong pang-unawa.
                </div>
                <form id="requestForm" action="submit_request.php" method="POST" enctype="multipart/form-data">
                    <!-- personal info section -->
<div class="form-section">
    <h5 class="mb-4">Personal Information of the Applicant</h5>
    <div class="row g-2">
        <div class="col-md-3">
            <label for="first_name" class="form-label required-field">First Name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" 
                   value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label for="middle_name" class="form-label">Middle Name</label>
            <input type="text" class="form-control" id="middle_name" name="middle_name" 
                   value="<?= htmlspecialchars($user_data['middle_name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label for="last_name" class="form-label required-field">Last Name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" 
                   value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label for="birthday" class="form-label required-field">Birthday</label>
            <input type="date" class="form-control" id="birthday" name="birthday" 
                   value="<?= htmlspecialchars($formatted_birthday) ?>" required>
        </div>
        <div class="col-md-2">
            <label for="family_relation" class="form-label required-field">Relation</label>
            <select class="form-select" id="family_relation" name="family_relation_id" required>
                <option value="" disabled>Select relation</option>
                <?php foreach ($family_relations as $relation): ?>
                    <option value="<?= $relation['id'] ?>" <?= ($relation['id'] == 1) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($relation['english_term']) ?> (<?= htmlspecialchars($relation['filipino_term']) ?>)
                    </option>
                <?php endforeach; ?>
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
        </div>
        <div class="col-md-6">
            <label for="complete_address" class="form-label required-field">Complete Address</label>
            <input type="text" class="form-control" id="complete_address" name="complete_address" 
                   value="<?= htmlspecialchars($user_data['address'] ?? '') ?>" required>
        </div>
        <div class="col-md-2">
            <label for="precint_number" class="form-label">Precinct Number</label>
            <input type="text" class="form-control" id="precint_number" name="precint_number">
        </div>
    </div>
</div>

                    <!-- program selection -->
                    <div class="form-section">
                        <h5 class="mb-4">Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <select class="form-select" id="program" name="assistance_id" required>
                                    <option value="" selected disabled>Select a program</option>
                                    <?php foreach ($assistance_types as $type): ?>
                                        <option value="<?= $type['id'] ?>" data-requirement="<?= htmlspecialchars($type['specific_requirement']) ?>">
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- sub-program selection (shown only for Medical/Financial Assistance) -->
                    <div class="form-section" id="subProgramSection" style="display: none;">
                        <h5 class="mb-4">Sub-Program</h5>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" id="subProgram" name="sub_program_id">
                                    <option value="" selected disabled>Select a sub-program</option>
                                    <!-- Options will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="otherSubProgram" name="assistance_name" 
                                       placeholder="Specify other type of assistance" style="display: none;">
                            </div>
                        </div>
                    </div>

                    <!-- program specific req section -->
                    <div class="form-section" id="specificRequirementSection" style="display: none;">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <div class="file-upload-container">
                                    <label for="specificRequest" class="file-upload-label required-field" id="specificRequirementName">Upload file</label>
                                    <p class="text-muted">Please upload the required document</p>
                                    <input type="file" class="form-control" id="specificRequest" name="specific_request" accept="image/*,.pdf" required>
                                    <img id="specificRequestPreview" class="file-preview" src="#" alt="Preview">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- standard requirements section -->
                    <div class="form-section">
                        <h5 class="mb-4">Required Documents</h5>
                        
                        <!-- brgy. indigency -->
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="file-upload-container">
                                    <label for="indigencyCert" class="file-upload-label required-field">Brgy. Indigency Certificate</label>
                                    <p class="text-muted">Upload a clear photo of the certificate</p>
                                    <input type="file" class="form-control" id="indigencyCert" name="indigency_cert" accept="image/*,.pdf" required>
                                    <img id="indigencyCertPreview" class="file-preview" src="#" alt="Preview">
                                </div>
                            </div>
                            
                            <!-- Photocopy ng id -->
                            <div class="col-md-6">
                                <div class="file-upload-container">
                                    <label for="idCopy" class="file-upload-label required-field">Photocopy of ID with Pulilan Address (Applicant)</label>
                                    <p class="text-muted">Upload a clear photo of the applicant's valid ID</p>
                                    <input type="file" class="form-control" id="idCopy" name="id_copy" accept="image/*,.pdf" required>
                                    <img id="idCopyPreview" class="file-preview" src="#" alt="Preview">
                                </div>
                            </div>
                            
                            <!-- Additional ID copy for non-self relations -->
                            <div class="col-md-6" id="idCopy2Container" style="display: none;">
                                <div class="file-upload-container">
                                    <label for="idCopy2" class="file-upload-label required-field">Photocopy of ID with Pulilan Address (Requester)</label>
                                    <p class="text-muted">Upload a clear photo of your valid ID</p>
                                    <input type="file" class="form-control" id="idCopy2" name="id_copy_2" accept="image/*,.pdf">
                                    <img id="idCopy2Preview" class="file-preview" src="#" alt="Preview">
                                </div>
                            </div>
                            
                            <!-- sulat kahilingan -->
                            <div class="col-md-6">
                                <div class="file-upload-container">
                                    <label for="requestLetter" class="file-upload-label required-field">Sulat kahilingan na nakapangalan kay Vice Mayor Atty. Imee Cruz</label>
                                    <p class="text-muted">Upload a clear photo of the request letter</p>
                                    <input type="file" class="form-control" id="requestLetter" name="request_letter" accept="image/*,.pdf" required>
                                    <img id="requestLetterPreview" class="file-preview" src="#" alt="Preview">
                                </div>
                            </div>
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
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- success modal -->
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

        // File preview functionality
        function readURL(input, previewId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    $(previewId).attr('src', e.target.result).show();
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#specificRequest").change(function() {
            readURL(this, '#specificRequestPreview');
        });
        
        $("#indigencyCert").change(function() {
            readURL(this, '#indigencyCertPreview');
        });
        
        $("#idCopy").change(function() {
            readURL(this, '#idCopyPreview');
        });
        
        $("#idCopy2").change(function() {
            readURL(this, '#idCopy2Preview');
        });
        
        $("#requestLetter").change(function() {
            readURL(this, '#requestLetterPreview');
        });

        // Handle family relation change to show/hide additional ID copy and disable/enable fields
        $('#family_relation').change(function() {
    const relationId = $(this).val();
    const idCopy2Container = $('#idCopy2Container');
    const idCopy2Input = $('#idCopy2');
    
    if (relationId && relationId == 1) { // 1 is Self
        // Use user's information and disable fields
        $('#first_name').val(originalUserData.firstName).prop('disabled', true).addClass('disabled-field');
        $('#middle_name').val(originalUserData.middleName).prop('disabled', true).addClass('disabled-field');
        $('#last_name').val(originalUserData.lastName).prop('disabled', true).addClass('disabled-field');
        $('#birthday').val(originalUserData.birthday).prop('disabled', true).addClass('disabled-field');
        $('#barangay').val(originalUserData.barangayId).prop('disabled', true).addClass('disabled-field');
        $('#complete_address').val(originalUserData.address).prop('disabled', true).addClass('disabled-field');
        
        // Enable precinct number
        $('#precint_number').prop('disabled', false).removeClass('disabled-field');
        
        idCopy2Container.hide();
        idCopy2Input.prop('required', false);
    } else {
        // Enable fields for editing
        $('#first_name').prop('disabled', false).removeClass('disabled-field').val('');
        $('#middle_name').prop('disabled', false).removeClass('disabled-field').val('');
        $('#last_name').prop('disabled', false).removeClass('disabled-field').val('');
        $('#birthday').prop('disabled', false).removeClass('disabled-field').val('');
        $('#barangay').prop('disabled', false).removeClass('disabled-field').val('');
        $('#complete_address').prop('disabled', false).removeClass('disabled-field').val('');
        
        idCopy2Container.show();
        idCopy2Input.prop('required', true);
    }
});

        // Initialize the form with Self relation selected
        $('#family_relation').trigger('change');

        // Sub-program handling
        $('#program').change(function() {
            const selectedOption = $(this).find('option:selected');
            const programId = selectedOption.val();
            const requirement = selectedOption.data('requirement');
            
            // Hide/show specific requirement section
            if (selectedOption.val()) {
                $('#specificRequirementSection').show();
                $('#specificRequirementName').text(requirement);
                $('#specificRequest').prop('required', true);
            } else {
                $('#specificRequirementSection').hide();
                $('#specificRequest').prop('required', false);
            }
            
            // Hide sub-program section by default
            $('#subProgramSection').hide();
            $('#otherSubProgram').hide().val('').prop('required', false);
            
            // Show sub-program section only for Medical (6) or Financial (4) Assistance
            if (programId == '6' || programId == '4') {
                $('#subProgramSection').show();
                
                // Clear and populate sub-program options
                $('#subProgram').empty().append('<option value="" selected disabled>Select a sub-program</option>');
                
                // Filter sub-programs by parent ID
                const subPrograms = <?php echo json_encode($sub_programs); ?>;
                const filteredSubs = subPrograms.filter(sub => sub.parent_id == programId);
                
                filteredSubs.forEach(sub => {
                    $('#subProgram').append(`<option value="${sub.id}">${sub.name}</option>`);
                });
                
                // Add "Others" option
                $('#subProgram').append('<option value="other">Others</option>');
            }
        });

        // Handle "Others" selection in sub-program
        $('#subProgram').change(function() {
            if ($(this).val() === 'other') {
                $('#otherSubProgram').show().prop('required', true);
            } else {
                $('#otherSubProgram').hide().prop('required', false).val('');
            }
        });

        // form submission handler
$('#requestForm').submit(async function(e) {
    e.preventDefault();
    // clear previous errors
    $('.is-invalid').removeClass('is-invalid');
    $('.text-danger').text('');
    $('.file-upload-container').css('border-color', '');

    // validate required fields
    let isValid = true;
    $(this).find('[required]').each(function() {
        if (!$(this).val()) {
            isValid = false;
            $(this).addClass('is-invalid');
            if ($(this).is('input[type="file"]') && $(this).prop('required')) {
                // Show error for empty file uploads
                const container = $(this).closest('.file-upload-container');
                container.css('border-color', 'red');
            }
        }
    });

    // Validate sub-program if Medical or Financial Assistance is selected
    const programId = $('#program').val();
    if ((programId == '6' || programId == '4') && !$('#subProgram').val()) {
        isValid = false;
        $('#subProgram').addClass('is-invalid');
    }
    
    if ($('#subProgram').val() === 'other' && !$('#otherSubProgram').val()) {
        isValid = false;
        $('#otherSubProgram').addClass('is-invalid');
    }

    // Validate additional ID copy ONLY if relation is not Self
    const relationId = $('#family_relation').val();
    if (relationId && relationId != 1) {
        if (!$('#idCopy2').val()) {
            isValid = false;
            $('#idCopy2').addClass('is-invalid');
            $('#idCopy2Container').find('.file-upload-container').css('border-color', 'red');
        }
    }

    if (!isValid) {
        Swal.fire('Error', 'Please fill all required fields', 'error');
        return;
    }

    // validate file sizes - only check files that are required and visible
    let filesValid = true;
    $('input[type="file"][required]').each(function() {
        // Skip validation for hidden files (like id_copy_2 when relation is Self)
        if ($(this).closest('.file-upload-container').is(':hidden')) {
            return true; // continue to next iteration
        }
        
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

    // prepare form data - enable disabled fields temporarily for form submission
    const formData = new FormData(this);
    
    // If fields are disabled, manually add their values to FormData
    if ($('#family_relation').val() == 1) {
        // For Self relation, ensure all user data is included even if fields are disabled
        const userData = {
            first_name: $('#first_name').val(),
            middle_name: $('#middle_name').val(),
            last_name: $('#last_name').val(),
            birthday: $('#birthday').val(),
            barangay_id: $('#barangay').val(),
            complete_address: $('#complete_address').val()
        };
        
        // Add each field to FormData if it's not already there
        Object.entries(userData).forEach(([key, value]) => {
            if (!formData.has(key)) {
                formData.append(key, value);
            }
        });
    }
    
    formData.append('user_id', <?= $_SESSION['user_id'] ?? 'null' ?>);

    // disable submit button
    const submitBtn = $('button[type="submit"]');
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

    try {
        // first check for existing request
        const checkResponse = await checkExistingRequest(formData);
        console.log('Check response:', checkResponse);

        if (checkResponse.exists) {
            Swal.fire({
                title: 'Existing Request Found',
                text: checkResponse.message,
                icon: 'warning'
            });
            return;
        }

        // if no existing request, submit the form
        const submitResponse = await submitRequest(formData);
        console.log('Submit response:', submitResponse);

        if (submitResponse.success) {
            $('#successModal').modal('show');
        } else {
            Swal.fire('Error', submitResponse.message || 'Submission failed', 'error');
        }
    } catch (error) {
        console.error('Full error details:', error);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        Swal.fire('Error', 'An error occurred. Please try again. ' + error.message, 'error');
    } finally {
        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
    }
});

// Update the checkExistingRequest function to handle FormData
async function checkExistingRequest(formData) {
    // Convert FormData to plain object for the check request
    const checkData = {};
    for (let [key, value] of formData.entries()) {
        // Skip file fields for the check request
        if (!(value instanceof File)) {
            checkData[key] = value;
        }
    }

    const response = await $.ajax({
        url: 'check_existing_request.php',
        type: 'POST',
        data: checkData,
        dataType: 'json'
    });

    return response;
}

        async function checkExistingRequest(formData) {
            // Get the birthday value from the form
            const birthdayValue = $('#birthday').val(); // This will be in YYYY-MM-DD format
            
            const checkData = {
                user_id: formData.get('user_id'),
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                middle_name: formData.get('middle_name'), // Include if needed
                barangay_id: formData.get('barangay_id'),
                assistance_id: formData.get('assistance_id'),
                birthday: birthdayValue // Send as proper date string
            };

            const response = await $.ajax({
                url: 'check_existing_request.php',
                type: 'POST',
                data: checkData,
                dataType: 'json'
            });

            return response;
        }

        // submit the request
        async function submitRequest(formData) {
            const response = await $.ajax({
                url: 'submit_request.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            });

            return response;
        }
    });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>