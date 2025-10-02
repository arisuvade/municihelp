<?php
session_start();
require_once '../../../includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['mayor_admin_id'])) {
    header('Location: ../../../includes/auth/login.php');
    exit;
}

// Get admin name for the badge
$mayor_admin_id = $_SESSION['mayor_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $mayor_admin_id");
$admin_name = $admin_query->fetch_assoc()['name'] ?? 'Admin';

$pageTitle = "Walk-in Form";
include '../../../includes/header.php';

// Get all online assistance types (parent programs where is_online = 1)
$online_programs = $conn->query("SELECT * FROM mswd_types WHERE parent_id IS NULL AND is_online = 1")->fetch_all(MYSQLI_ASSOC);

// Fetch sub-programs (children)
$sub_programs = $conn->query("SELECT * FROM mswd_types WHERE parent_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);

// Fetch barangays
$barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Helper to check if program has children
function has_children($type_id, $sub_programs) {
    foreach ($sub_programs as $sub) {
        if ($sub['parent_id'] == $type_id) return true;
    }
    return false;
}

// Get current Sulong Dulong beneficiary count
$sulongDulongCount = $conn->query("SELECT COUNT(*) as total FROM sulong_dulong_beneficiaries")->fetch_assoc()['total'];
$maxBeneficiaries = 800;
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
            --munici-green: #4CAF50;
            --munici-green-light: #E8F5E9;
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
            background: linear-gradient(135deg, var(--munici-green), #2E7D32);
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
            background-color: #3e8e41;
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
        
        #sulongDulongCounter {
            font-size: 1.1rem;
            padding: 10px 15px;
            border-left: 4px solid #6c757d;
            background-color: #f8f9fa;
            margin-top: 1rem;
            display: none;
        }
        
        #currentCount {
            font-weight: bold;
            color: #4361ee;
        }
        
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
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">MSWD Walk-in Form</h1>
                    <p class="mb-0">Admin walk-in MSWD request form</p>
                </div>
            </div>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($admin_name) ?></span>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="request-form-card card">
            <div class="card-header">
                Walk-in MSWD Request Form
            </div>
            <div class="card-body">
                <!-- Program Selection Form -->
                <form id="programSelectionForm">
                    <div class="form-section">
                        <h5 class="mb-4">Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <select class="form-select" id="program" name="assistance_id" required>
                                    <option value="" selected disabled>Select a program</option>
                                    <?php foreach ($online_programs as $type): ?>
                                        <option value="<?= $type['id'] ?>" 
                                                data-is-online="<?= $type['is_online'] ?>"
                                                data-has-children="<?= has_children($type['id'], $sub_programs) ?>"
                                                <?= $type['id'] == 16 ? 'data-is-others="true"' : '' ?>>
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
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
                                <input type="text" class="form-control" id="othersInput" name="assistance_name" placeholder="Please specify the assistance they need">
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Online Form Section -->
                <div id="onlineFormSection">
                    <form id="onlineForm" action="submit_walkin.php" method="POST">
                        <input type="hidden" name="mayor_admin_id" value="<?= $_SESSION['mayor_admin_id'] ?>">
                        <input type="hidden" name="assistance_id" id="formAssistanceId">
                        <input type="hidden" name="sub_program_id" id="formSubProgramId">
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h5 class="mb-4">Personal Information of the Applicant</h5>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name">
                                </div>
                                <div class="col-md-3">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="birthday" class="form-label required-field">Birthday</label>
                                    <input type="date" class="form-control" id="birthday" name="birthday" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="barangay" class="form-label required-field">Barangay</label>
                                    <select class="form-select" id="barangay" name="barangay_id" required>
                                        <option value="" selected disabled>Select barangay</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?= $barangay['id'] ?>"><?= htmlspecialchars($barangay['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="complete_address" class="form-label required-field">Complete Address</label>
                                    <input type="text" class="form-control" id="complete_address" name="complete_address" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="contact_no" class="form-label required-field">Contact Number</label>
                                    <input type="text" class="form-control" id="contact_no" name="contact_no" required>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-submit" id="submitButton">
                                <i class="fas fa-paper-plane"></i> Submit Walk-in Request
                            </button>
                        </div>
                    </form>
                    <!-- Sulong Dulong Counter -->
                <div id="sulongDulongCounter">
                    <strong>Beneficiary Count:</strong> 
                    <span id="currentCount"><?= $sulongDulongCount ?></span>/<?= $maxBeneficiaries ?>
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
                    <h4 class="mb-3">Walk-in Request Submitted Successfully!</h4>
                    <p class="mb-4">The walk-in request has been recorded in the system.</p>
                    <button type="button" class="btn btn-success" id="closeSuccessModal">
                        Close
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
        const subPrograms = <?php echo json_encode($sub_programs); ?>;
        const maxBeneficiaries = <?= $maxBeneficiaries ?>;
        let selectedProgramType = null;
        let isLimitReached = false;

        // Close modal handler
        $('#closeSuccessModal').click(function() {
            window.location.reload();
        });

        // Program selection change handler
        $('#program').change(function() {
            const selectedOption = $(this).find('option:selected');
            const selectedValue = selectedOption.val();
            const isOthers = selectedOption.data('is-others') === true;
            
            selectedProgramType = {
                id: selectedValue,
                isOnline: selectedOption.data('is-online'),
                hasChildren: selectedOption.data('has-children'),
                isOthers: isOthers
            };

            // Update hidden form fields
            $('#formAssistanceId').val(selectedValue);
            $('#formSubProgramId').val('');

            // Reset and hide sections
            $('#subProgramSection').hide();
            $('#othersInputSection').hide();

            // Update counter
            updateSulongDulongCounter();

            // Show others input if "Others" is selected
            if (selectedProgramType.isOthers) {
                $('#othersInputSection').show();
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
                updateSulongDulongCounter();
            }
        });

        // Function to update Sulong Dulong counter
        function updateSulongDulongCounter() {
            const assistanceId = $('#subProgram').val() || $('#program').val();
            const isSulongDulong = [33, 34, 35].includes(parseInt(assistanceId));
            
            if (isSulongDulong) {
                // Fetch current count via AJAX
                $.ajax({
                    url: '../../../mayor/mswd/get_beneficiary_count.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#currentCount').text(response.count);
                            $('#sulongDulongCounter').show();
                            
                            // Disable submit button if limit reached
                            isLimitReached = response.count >= maxBeneficiaries;
                            $('#submitButton').prop('disabled', isLimitReached);
                            
                            if (isLimitReached) {
                                $('#sulongDulongCounter').addClass('text-danger');
                            } else {
                                $('#sulongDulongCounter').removeClass('text-danger');
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

        // Form submission handler
        $('#onlineForm').submit(async function(e) {
            e.preventDefault();
            
            // If limit reached, prevent submission
            if (isLimitReached) {
                Swal.fire('Error', 'Maximum number of beneficiaries reached (800). Cannot submit new requests.', 'error');
                return;
            }

            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.text-danger').text('');

            // Validate required fields
            let isValid = true;
            $(this).find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                }
            });

            // If Others is selected, validate the assistance_name field
            if ($('#program').find('option:selected').data('is-others') && !$('#othersInput').val()) {
                isValid = false;
                $('#othersInput').addClass('is-invalid');
            }

            if (!isValid) {
                Swal.fire('Error', 'Please fill all required fields', 'error');
                return;
            }

            // Disable submit button
            const submitBtn = $('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            try {
                // First check for existing request
                const checkResponse = await $.ajax({
                    url: 'check_existing_walkin.php',
                    type: 'POST',
                    data: {
                        first_name: $('#first_name').val(),
                        last_name: $('#last_name').val(),
                        middle_name: $('#middle_name').val(),
                        barangay_id: $('#barangay').val(),
                        birthday: $('#birthday').val(),
                        assistance_id: $('#subProgram').val() || $('#program').val()
                    },
                    dataType: 'json'
                });

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

                    await Swal.fire(alertOptions);
                    return;
                }

                // Prepare form data
                const formData = new FormData(this);
                if ($('#program').find('option:selected').data('is-others')) {
                    formData.append('assistance_name', $('#othersInput').val());
                }

                // Submit the form with proper content type
                const submitResponse = await $.ajax({
                    url: 'submit_walkin.php',
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
                console.error('Submission error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'An error occurred. Please try again.',
                    icon: 'error'
                });
            } finally {
                submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Walk-in Request');
            }
        });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>