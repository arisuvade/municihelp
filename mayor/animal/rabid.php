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

// Fetch barangays
$barangays = $conn->query("SELECT * FROM barangays ORDER by name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Rabid Report - Form";
include '../../includes/header.php';
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
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            width: 95%;
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
        
        .report-form-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .report-form-card .card-header {
            text-align: center;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .required-field::after {
            content: " *";
            color: red;
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

        .disabled-field {
            background-color: #f8f9fa;
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .btn-submit {
                width: 100%;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Rabid Dog Report</h1>
                    <p class="mb-0">Fill out the form to report a suspected rabid dog in your area</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="report-form-card card">
            <div class="card-header">
                Rabid Report Form
            </div>
            <div class="card-body">
                <form id="reportForm" action="submit_rabid_report.php" method="POST" enctype="multipart/form-data">
                    <!-- Reporter Information -->
                    <div class="form-section">
                        <h5 class="mb-3">Your Information</h5>
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

                    <!-- Incident Details -->
                    <div class="form-section">
                        <h5 class="mb-3">Incident Details</h5>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label for="location" class="form-label required-field">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required placeholder="e.g. Near the market, beside the church">
                            </div>
                            <div class="col-md-2">
                                <label for="date" class="form-label required-field">Date</label>
                                <input type="date" class="form-control" id="date" name="date" required>
                            </div>
                            <div class="col-md-2">
                                <label for="time" class="form-label required-field">Time</label>
                                <input type="time" class="form-control" id="time" name="time" required>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label required-field">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Describe the animal appearance and behavior"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Evidence Upload -->
                    <div class="form-section">
                        <h5 class="mb-3">Evidence</h5>
                        <div class="file-upload-container">
                            <label for="proof" class="file-upload-label required-field">Upload photo of the animal</label>
                            <p class="text-muted">Please upload a clear photo of the suspected rabid animal</p>
                            <input type="file" id="proof" name="proof" accept="image/*" class="form-control" required>
                            <img id="proofPreview" class="file-preview" src="#" alt="Preview">
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Report
                        </button>
                    </div>
                </form>
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
                    <h4 class="mb-3">Report Submitted Successfully!</h4>
                    <p class="mb-4">Thank you for helping keep our community safe.</p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="window.location.href='../../mayor/animal/status.php'">
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

        // Set default date to today
        const today = new Date().toISOString().split('T')[0];
        $('#date').val(today);
        
        // Set default time to now
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        $('#time').val(`${hours}:${minutes}`);

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

        $("#proof").change(function() {
            readURL(this, '#proofPreview');
        });

        // Form submission
        $('#reportForm').submit(function(e) {
            e.preventDefault();
            
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
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

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

            // Validate file size
            let fileValid = true;
            const proofFile = $('#proof')[0].files[0];
            if (!proofFile) {
                fileValid = false;
                $('#proof').addClass('is-invalid');
                $('#proof').closest('.file-upload-container').css('border-color', 'red');
            } else if (proofFile.size > 5 * 1024 * 1024) { // 5MB limit
                fileValid = false;
                $('#proof').addClass('is-invalid');
                $('#proof').closest('.file-upload-container').css('border-color', 'red')
                    .find('.text-muted').addClass('text-danger').text('File too large (max 5MB)');
            }

            if (!isValid || !fileValid) {
                Swal.fire('Error', 'Please check all required fields and ensure your file is valid', 'error');
                submitBtn.prop('disabled', false).html(originalText);
                return;
            }

            // Create FormData and add user_id
            const formData = new FormData(this);
            formData.append('user_id', <?= json_encode($_SESSION['user_id']) ?>);

            $.ajax({
                url: 'submit_rabid_report.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#successModal').modal('show');
                        $('#reportForm')[0].reset();
                        $('#proofPreview').hide();
                        // Reset date/time to current
                        $('#date').val(today);
                        $('#time').val(`${hours}:${minutes}`);
                        // Reset relation to Self
                        $('#relation').val('self').trigger('change');
                    } else {
                        Swal.fire('Error', response.message || 'Submission failed', 'error');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'An error occurred';
                    try {
                        const json = JSON.parse(xhr.responseText);
                        errorMsg = json.message || errorMsg;
                    } catch (e) {
                        errorMsg = xhr.statusText || errorMsg;
                    }
                    Swal.fire('Error', errorMsg, 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
    });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>