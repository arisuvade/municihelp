<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT name, middle_name, last_name, birthday, address, barangay_id, phone FROM users WHERE id = ?");
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

if (!isset($_GET['dog_id'])) {
    header('Location: claiming.php');
    exit;
}

$dogId = (int)$_GET['dog_id'];
$dog = $conn->query("
    SELECT d.id, d.breed, d.color, d.location_found, d.image_path, d.date_caught
    FROM dogs d
    WHERE d.id = $dogId AND d.status = 'for_claiming'
")->fetch_assoc();

if (!$dog) {
    header('Location: claiming.php');
    exit;
}

// Fetch barangays
$barangays = $conn->query("SELECT * FROM barangays ORDER by name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Lost Dog Claims - Form";
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
        
        .dog-preview {
            display: flex;
            gap: 20px;
            margin-bottom: 2rem;
            align-items: center;
        }
        
        .dog-preview-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid var(--munici-green);
        }
        
        .dog-preview-info {
            flex: 1;
        }
        
        .dog-preview-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--munici-green);
        }
        
        .dog-preview-details {
            color: #555;
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

        .disabled-field {
            background-color: #f8f9fa;
            opacity: 1;
        }

        /* Availability table styles */ .availability-table { width: 100%; border-collapse: collapse; margin-top: 1rem; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); } .availability-table th { background-color: var(--munici-green); color: white; padding: 12px; text-align: left; } .availability-table td { padding: 12px; border-bottom: 1px solid #eee; } .availability-table tr:last-child td { border-bottom: none; } .availability-table tr:hover { background-color: #f8f9fa; }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dog-preview {
                flex-direction: column;
                text-align: center;
            }
            
            .dog-preview-image {
                width: 120px;
                height: 120px;
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
                    <h1 class="mb-1">Claim Dog</h1>
                    <p class="mb-0">Fill out the form to claim this dog.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="request-form-card card">
            <div class="card-header">
                Lost Dog Claim Form
            </div>
            <div class="card-body">
                <!-- Dog Preview Section -->
                <div class="dog-preview">
                    <img src="<?= '/' . htmlspecialchars($dog['image_path'] ?? 'assets/default-dog.jpg') ?>"
                         class="dog-preview-image" 
                         alt="Dog image">
                    <div class="dog-preview-info">
                        <div class="dog-preview-name">
                            <?= htmlspecialchars($dog['breed'] ?: 'Unknown Breed') ?>
                        </div>
                        <div class="dog-preview-details">
                            <div><strong>Color:</strong> <?= htmlspecialchars($dog['color'] ?: 'Unknown') ?></div>
                            <div><strong>Location Found:</strong> <?= htmlspecialchars($dog['location_found']) ?></div>
                            <div><strong>Date Caught:</strong> <?= date('M d, Y', strtotime($dog['date_caught'])) ?></div>
                        </div>
                    </div>
                </div>

                <form id="claimForm" action="submit_claim.php" method="POST">
                    <input type="hidden" name="dog_id" value="<?= $dog['id'] ?>">
                    
                    <!-- Claimant Information -->
                    <div class="form-section">
                        <h5 class="mb-4">Claimant Information</h5>
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
                            <div class="col-md-6">
                                <label for="complete_address" class="form-label required-field">Complete Address</label>
                                <input type="text" class="form-control" id="complete_address" name="complete_address" 
                                       value="<?= htmlspecialchars($user_data['address'] ?? '') ?>" required>
                                <input type="hidden" name="complete_address" value="<?= htmlspecialchars($user_data['address'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
    <label for="phone" class="form-label required-field">Contact Number</label>
    <div class="input-group">
        <span class="input-group-text">+63</span>
        <input type="text" class="form-control" id="phone" name="phone_raw"
               value="<?= htmlspecialchars(substr($user_data['phone'] ?? '', -10)) ?>"
               pattern="[0-9]{10}" maxlength="10"
               placeholder="9123456789" required>
    </div>
    <input type="hidden" name="phone" id="phone_hidden">
</div>
                        </div>
                    </div>

                    <!-- Dog Information -->
                    <div class="form-section">
                        <h5 class="mb-4">Dog Information</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="name_of_dog" class="form-label">Dog's Name (if known)</label>
                                <input type="text" class="form-control" id="name_of_dog" name="name_of_dog">
                            </div>
                            <div class="col-md-6">
                                <label for="age_of_dog" class="form-label">Dog's Age (if known)</label>
                                <input type="number" class="form-control" id="age_of_dog" name="age_of_dog" min="0" max="30" placeholder="In years">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
    <h5 class="mb-4">Additional Information</h5>
    <div class="row g-2">
        <div class="col-md-12">
            <label for="remarks" class="form-label">Remarks</label>
            <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                      placeholder="Please specify (e.g., identifying marks, special characteristics, or other information to help verify ownership)"></textarea>
        </div>
    </div>
</div>

                    <!-- Claim Fines -->
                    <div class="form-section">
                        <h5 class="mb-4">Claim Fines</h5>
                        <table class="availability-table">
                            <colgroup>
                                <col style="width:50%">
                                <col style="width:50%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Offense</th>
                                    <th>Fine Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1st Offense</td>
                                    <td>₱300</td>
                                </tr>
                                <tr>
                                    <td>2nd Offense</td>
                                    <td>₱500</td>
                                </tr>
                                <tr>
                                    <td>3rd Offense</td>
                                    <td>₱800</td>
                                </tr>
                            </tbody>
                        </table>
                        <small class="d-block mt-2" style="color: var(--munici-green); font-weight: 500;">
                            *Fines apply per claimant and increase with repeated offenses.
                        </small>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Claim Request
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
                    <h4 class="mb-3">Claim Submitted Successfully!</h4>
                    <p class="mb-4">Your claim request has been received and is pending approval.</p>
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
            address: "<?= addslashes($user_data['address'] ?? '') ?>",
            phone: "<?= addslashes(substr($user_data['phone'] ?? '', 3)) ?>"
        };

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
                $('#phone').val(originalUserData.phone).prop('disabled', true).addClass('disabled-field');
                
                // Update hidden fields with user data
                $('input[name="first_name"]').val(originalUserData.firstName);
                $('input[name="middle_name"]').val(originalUserData.middleName);
                $('input[name="last_name"]').val(originalUserData.lastName);
                $('input[name="birthday"]').val("<?= $user_data['birthday'] ?? '' ?>");
                $('input[name="barangay_id"]').val(originalUserData.barangayId);
                $('input[name="complete_address"]').val(originalUserData.address);
                $('input[name="phone"]').val("+63" + originalUserData.phone);
            } else {
                // Enable fields for editing and clear them
                $('#first_name').prop('disabled', false).removeClass('disabled-field').val('');
                $('#middle_name').prop('disabled', false).removeClass('disabled-field').val('');
                $('#last_name').prop('disabled', false).removeClass('disabled-field').val('');
                $('#birthday').prop('disabled', false).removeClass('disabled-field').val('');
                $('#barangay').prop('disabled', false).removeClass('disabled-field').val('');
                $('#complete_address').prop('disabled', false).removeClass('disabled-field').val('');
                $('#phone').prop('disabled', false).removeClass('disabled-field').val('');
                
                // Clear hidden fields
                $('input[name="first_name"]').val('');
                $('input[name="middle_name"]').val('');
                $('input[name="last_name"]').val('');
                $('input[name="birthday"]').val('');
                $('input[name="barangay_id"]').val('');
                $('input[name="complete_address"]').val('');
                $('input[name="phone"]').val('');
            }
        });

        // Initialize the form with Self relation selected
        $('#relation').trigger('change');

        // Phone number validation
        $('#phone').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });

        $('#claimForm').submit(function(e) {
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
                
                // Format phone number with +63
                const phoneValue = $('#phone').val();
                if (phoneValue) {
                    $('input[name="phone"]').val('+63' + phoneValue);
                }
            }
            // For "Self", the hidden fields already contain the correct values
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

            // Create FormData object
            const formData = new FormData(this);
            formData.append('user_id', <?= json_encode($_SESSION['user_id'] ?? null) ?>);

            $.ajax({
                url: 'submit_claim.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#successModal').modal('show');
                    } else {
                        Swal.fire('Error', response.message || 'Submission failed', 'error');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'An error occurred';
                    try {
                        var json = JSON.parse(xhr.responseText);
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