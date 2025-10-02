<?php
session_start();
require_once '../../../includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['animal_admin_id'])) {
    die("Admin access required");
}

// Get all dogs available for claiming or adoption, sorted by ID descending
$dogs = $conn->query("
    SELECT d.id, d.breed, d.color, d.status 
    FROM dogs d 
    WHERE d.status IN ('for_claiming', 'for_adoption')
    ORDER BY d.id DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Walk-in Form";
include '../../../includes/header.php';
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
            background: linear-gradient(135deg, var(--munici-green), #2E7D32);
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
        
        #adoptionInfoSection {
            display: none;
        }
        
        .dog-type-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .dog-type-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .dog-type-btn.active {
            border-color: var(--munici-green);
            background-color: var(--munici-green-light);
        }
        
        .dog-list-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
        }
        
        .dog-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .dog-item:hover {
            background-color: #f8f9fa;
        }
        
        .dog-item.selected {
            background-color: var(--munici-green-light);
            border-left: 3px solid var(--munici-green);
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Claim and Adoption Walk-in Form</h1>
                    <p class="mb-0">Admin walk-in claim and adoption request form</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="request-form-card card">
            <div class="card-header">
                Walk-in Claim and Adoption Request Form
            </div>
            <div class="card-body">
                <form id="walkinForm" action="submit_walkin.php" method="POST">
                    <input type="hidden" name="animal_admin_id" value="<?= $_SESSION['animal_admin_id'] ?>">
                    <input type="hidden" id="dog_id" name="dog_id" value="">
                    <input type="hidden" id="is_claiming" name="is_claiming" value="1"> <!-- Default to claiming -->
                    
                    <!-- Dog Selection -->
                    <div class="form-section">
                        <h5 class="mb-4">Select Dog</h5>
                        
                        <div class="dog-type-buttons">
                            <div class="dog-type-btn active" data-type="claiming">
                                <h5>For Claiming</h5>
                            </div>
                            <div class="dog-type-btn" data-type="adoption">
                                <h5>For Adoption</h5>
                            </div>
                        </div>
                        
                        <div class="dog-list-container" id="claimingDogs">
                            <?php 
                            $claimingDogs = array_filter($dogs, fn($dog) => in_array($dog['status'], ['for_claiming', 'pending_claim']));
                            foreach ($claimingDogs as $dog): ?>
                                <div class="dog-item" data-id="<?= $dog['id'] ?>" data-status="<?= $dog['status'] ?>">
                                    <strong>ID <?= $dog['id'] ?></strong> - <?= htmlspecialchars($dog['breed']) ?> 
                                    (<?= htmlspecialchars($dog['color']) ?>)
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($claimingDogs)): ?>
                                <div class="text-muted">No dogs available for claiming</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="dog-list-container" id="adoptionDogs" style="display:none;">
                            <?php 
                            $adoptionDogs = array_filter($dogs, fn($dog) => in_array($dog['status'], ['for_adoption', 'pending_adoption']));
                            foreach ($adoptionDogs as $dog): ?>
                                <div class="dog-item" data-id="<?= $dog['id'] ?>" data-status="<?= $dog['status'] ?>">
                                    <strong>ID <?= $dog['id'] ?></strong> - <?= htmlspecialchars($dog['breed']) ?> 
                                    (<?= htmlspecialchars($dog['color']) ?>)
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($adoptionDogs)): ?>
                                <div class="text-muted">No dogs available for adoption</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Person Information -->
                    <div class="form-section">
                        <h5 class="mb-4">Person Information</h5>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="first_name" class="form-label required-field">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="col-md-4">
                                <label for="last_name" class="form-label required-field">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="col-12">
                                <label for="complete_address" class="form-label required-field">Complete Address</label>
                                <input type="text" class="form-control" id="complete_address" name="complete_address" required>
                            </div>
                        </div>
                    </div>

                    <!-- Dog Information (for claiming only) -->
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

                    <!-- Adoption Information (for adoption only) -->
                    <div id="adoptionInfoSection" class="form-section">
                        <h5 class="mb-4">Adoption Information</h5>
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="adoption_reason" class="form-label">Reason for Adoption</label>
                                <textarea class="form-control" id="adoption_reason" name="adoption_reason" rows="3"></textarea>
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

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h4 class="mb-3">Submission Successful!</h4>
                    <p class="mb-4" id="successMessage">The request has been processed.</p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
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
        // Default to showing claiming dogs and dog info section
        $('#claimingDogs').show();
        $('#adoptionDogs').hide();
        
        // Toggle between claiming and adoption dogs
        $('.dog-type-btn').click(function() {
            $('.dog-type-btn').removeClass('active');
            $(this).addClass('active');
            
            const type = $(this).data('type');
            $('#is_claiming').val(type === 'claiming' ? '1' : '0');
            
            if (type === 'claiming') {
                $('#claimingDogs').show();
                $('#adoptionDogs').hide();
                $('.form-section:has(#name_of_dog)').show();
                $('#adoptionInfoSection').hide();
                $('#adoption_reason').removeAttr('required');
            } else {
                $('#claimingDogs').hide();
                $('#adoptionDogs').show();
                $('.form-section:has(#name_of_dog)').hide();
                $('#adoptionInfoSection').show();
                $('#adoption_reason').attr('required', 'required');
            }
            
            // Clear any selected dog
            $('.dog-item').removeClass('selected');
            $('#dog_id').val('');
        });

        // Select a dog
        $('.dog-item').click(function() {
            $('.dog-item').removeClass('selected');
            $(this).addClass('selected');
            $('#dog_id').val($(this).data('id'));
        });

        $('#walkinForm').submit(function(e) {
            e.preventDefault();
            
            if (!$('#dog_id').val()) {
                Swal.fire('Error', 'Please select a dog', 'error');
                return;
            }
            
            const isAdoption = $('#is_claiming').val() === '0';
            if (isAdoption && !$('#adoption_reason').val().trim()) {
                Swal.fire('Error', 'Please provide a reason for adoption', 'error');
                return;
            }

            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            // Create FormData object
            const formData = new FormData(this);

            $.ajax({
                url: 'submit_walkin.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#successMessage').text(response.message);
                        $('#successModal').modal('show');
                        $('#walkinForm')[0].reset();
                        $('.dog-item').removeClass('selected');
                        $('#dog_id').val('');
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

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>