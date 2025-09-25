<?php
session_start();
require_once '../../../includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['assistance_admin_id'])) {
    header('Location: ../../../includes/auth/login.php');
    exit;
}

// Get admin name for the badge
$assistance_admin_id = $_SESSION['assistance_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $assistance_admin_id");
$admin_name = $admin_query->fetch_assoc()['name'] ?? 'Admin';

$pageTitle = "Walk-in Form";
include '../../../includes/header.php';

// Get main assistance types and other data
$assistance_types = $conn->query("SELECT * FROM assistance_types WHERE parent_id IS NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$sub_programs = $conn->query("SELECT * FROM assistance_types WHERE parent_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
$barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$family_relations = $conn->query("SELECT * FROM family_relations ORDER BY id")->fetch_all(MYSQLI_ASSOC);
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
        
        .required-field::after {
            content: " *";
            color: red;
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
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Assistance Walk-in Form</h1>
                    <p class="mb-0">Admin walk-in assistance request form</p>
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
                Walk-in Assistance Request Form
            </div>
            <div class="card-body">
                <form id="walkinForm" action="submit_walkin.php" method="POST">
                    <!-- personal info section -->
                    <div class="form-section">
                        <h5 class="mb-4">Applicant's Information</h5>
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
                            <div class="col-md-2">
                                <label for="family_relation" class="form-label required-field">Relation</label>
                                <select class="form-select" id="family_relation" name="family_relation_id" required>
                                    <option value="" selected disabled>Select relation</option>
                                    <?php foreach ($family_relations as $relation): ?>
                                        <option value="<?= $relation['id'] ?>">
                                            <?= htmlspecialchars($relation['english_term']) ?> (<?= htmlspecialchars($relation['filipino_term']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
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
                            <div class="col-md-2">
                                <label for="precint_number" class="form-label">Precinct Number</label>
                                <input type="text" class="form-control" id="precint_number" name="precint_number">
                            </div>
                        </div>
                    </div>

                    <!-- program selection -->
                    <div class="form-section">
                        <h5 class="mb-4">Assistance Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="program" class="form-label required-field">Program</label>
                                <select class="form-select" id="program" name="assistance_id" required>
                                    <option value="" selected disabled>Select a program</option>
                                    <?php foreach ($assistance_types as $type): ?>
                                        <option value="<?= $type['id'] ?>">
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

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Walk-in Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success modal -->
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
        $('#closeSuccessModal').click(function() {
        window.location.reload();
    });
        // Sub-program handling
        $('#program').change(function() {
            const programId = $(this).val();
            
            $('#subProgramSection').hide();
            $('#otherSubProgram').hide().val('').prop('required', false);
            
            // Show sub-program section only if the selected program has sub-programs
            const subPrograms = <?php echo json_encode($sub_programs); ?>;
            const hasSubPrograms = subPrograms.some(sub => sub.parent_id == programId);
            
            if (hasSubPrograms) {
                $('#subProgramSection').show();
                $('#subProgram').empty().append('<option value="" selected disabled>Select a sub-program</option>');
                
                // Filter sub-programs for this parent
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

        // Form submission handler
        $('#walkinForm').submit(async function(e) {
            e.preventDefault();
            
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

            if (!isValid) {
                Swal.fire('Error', 'Please fill all required fields', 'error');
                return;
            }

            // Prepare form data
            const formData = new FormData(this);
            formData.append('assistance_admin_id', <?= $_SESSION['assistance_admin_id'] ?>);

            // First check for existing request
            try {
                const checkResponse = await $.ajax({
                    url: 'check_existing_walkin.php',
                    type: 'POST',
                    data: {
                        first_name: formData.get('first_name'),
                        middle_name: formData.get('middle_name'),
                        last_name: formData.get('last_name'),
                        barangay_id: formData.get('barangay_id'),
                        birthday: formData.get('birthday')
                    },
                    dataType: 'json'
                });

                if (checkResponse.exists) {
                    Swal.fire({
                        title: 'Existing Request Found',
                        text: checkResponse.message,
                        icon: 'warning'
                    });
                    return;
                }
            } catch (error) {
                console.error("Error checking existing request:", error);
                Swal.fire('Error', 'Failed to check for existing requests. Please try again.', 'error');
                return;
            }

            // Disable submit button
            const submitBtn = $('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            // Submit the form
            try {
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
                    $('#walkinForm')[0].reset();
                } else {
                    Swal.fire('Error', submitResponse.message || 'Submission failed', 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Walk-in Request');
            }
        });

        
    });
    </script>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>