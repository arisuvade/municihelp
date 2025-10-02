<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['animal_admin_id']) && !isset($_SESSION['pound_admin_id'])) {
    header('Location: /mayor/includes/auth/login.php');
    exit;
}

$dogId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dog = $conn->query("SELECT * FROM dogs WHERE id = $dogId")->fetch_assoc();

if (!$dog) {
    die("Dog not found");
}

// Get user's claims and adoptions if logged in as user
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_claims = [];
$user_adoptions = [];
$pending_claims = [];
$pending_adoptions = [];

if ($user_id) {
    // Fetch user's pending/approved claims
    $user_claims_result = $conn->query("
        SELECT dog_id, status FROM dog_claims 
        WHERE user_id = $user_id 
        AND (status = 'pending' OR status = 'approved')
    ");
    
    while ($row = $user_claims_result->fetch_assoc()) {
        $user_claims[$row['dog_id']] = $row['status'];
    }
    
    // Fetch user's pending/approved adoptions
    $user_adoptions_result = $conn->query("
        SELECT dog_id, status FROM dog_adoptions 
        WHERE user_id = $user_id 
        AND (status = 'pending' OR status = 'approved')
    ");
    
    while ($row = $user_adoptions_result->fetch_assoc()) {
        $user_adoptions[$row['dog_id']] = $row['status'];
    }
}

// Fetch all pending/approved claims for this dog
$all_pending_claims = $conn->query("
    SELECT dog_id, status FROM dog_claims 
    WHERE dog_id = $dogId 
    AND (status = 'pending' OR status = 'approved')
");

while ($row = $all_pending_claims->fetch_assoc()) {
    $pending_claims[$row['dog_id']] = $row['status'];
}

// Fetch all pending/approved adoptions for this dog
$all_pending_adoptions = $conn->query("
    SELECT dog_id, status FROM dog_adoptions 
    WHERE dog_id = $dogId 
    AND (status = 'pending' OR status = 'approved')
");

while ($row = $all_pending_adoptions->fetch_assoc()) {
    $pending_adoptions[$row['dog_id']] = $row['status'];
}

// Get claim details if claimed
$claimDetails = null;
if ($dog['status'] == 'claimed') {
    $claim = $conn->query("SELECT handover_photo_path, name_of_dog, age_of_dog FROM dog_claims WHERE dog_id = $dogId AND status = 'completed' LIMIT 1")->fetch_assoc();
    if ($claim) {
        $claimDetails = $claim;
    }
}

// Handle delete if admin and delete request submitted
$isAdmin = isset($_SESSION['animal_admin_id']) || isset($_SESSION['pound_admin_id']);
$allowedStatusForDelete = ['for_claiming', 'for_adoption'];
$deleted = false;

if ($isAdmin && in_array($dog['status'], $allowedStatusForDelete) && isset($_POST['delete_dog'])) {
    // Confirmation check
    if ($_POST['confirm_delete'] === 'DELETE') {
        $deleteStmt = $conn->prepare("DELETE FROM dogs WHERE id = ?");
        $deleteStmt->bind_param("i", $dogId);
        if ($deleteStmt->execute()) {
            $deleted = true;
        } else {
            $deleteError = "Failed to delete dog record.";
        }
    } else {
        $deleteError = "Confirmation text was not entered correctly.";
    }
}

$pageTitle = "Dog Details";
include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="/mayor/assets/favicon.ico" type="image/x-icon">
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
        
        .dog-details-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .dog-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%; /* 4:3 Aspect Ratio (3/4 = 0.75) */
            overflow: hidden;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .dog-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dog-detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .dog-detail-label {
            font-weight: 600;
            color: var(--munici-green);
        }
        
        .btn-action {
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-claim {
            background-color: var(--munici-green);
            color: white;
            border: none;
        }
        
        .btn-claim:hover {
            background-color: #0E3B85;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-back {
            background-color: #f0f0f0;
            color: var(--dark-color);
            border: 1px solid #ddd;
        }
        
        .btn-back:hover {
            background-color: #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dog-image {
                max-height: 300px;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        .btn-danger {
            background-color: #DC3545;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background-color: #C82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .delete-confirm-modal .modal-header {
            background-color: #f8d7da;
            color: #721c24;
            border-bottom: 1px solid #f5c6cb;
        }
        
        .btn-action:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .btn-action:disabled:hover {
            background-color: #6c757d;
            transform: none;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <?php if (!$deleted): ?>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Dog Details</h1>
                    <p class="mb-0">Complete information about this dog.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if (isset($deleteError)): ?>
            <div class="alert alert-danger"><?= $deleteError ?></div>
        <?php endif; ?>
        
        <div class="dog-details-card card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="dog-image-container">
                            <img src="<?= '/' . htmlspecialchars($claimDetails['handover_photo_path'] ?? $dog['image_path'] ?? 'assets/default-dog.jpg') ?>"
                                class="dog-image" 
                                alt="Dog image">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Dog ID:</span>
                            <?= $dog['id'] ?>
                        </div>
    
                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Status:</span>
                            <?= ucwords(str_replace('_', ' ', $dog['status'])) ?>
                        </div>

                        <?php if ($dog['status'] == 'claimed' && $claimDetails && (!empty($claimDetails['name_of_dog']) || $claimDetails['age_of_dog'] > 0)): ?>
                            <?php if (!empty($claimDetails['name_of_dog'])): ?>
                                <div class="dog-detail-item">
                                    <span class="dog-detail-label">Name:</span>
                                    <?= htmlspecialchars($claimDetails['name_of_dog']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($claimDetails['age_of_dog'] > 0): ?>
                                <div class="dog-detail-item">
                                    <span class="dog-detail-label">Age:</span>
                                    <?= htmlspecialchars($claimDetails['age_of_dog']) ?> years old
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Breed:</span>
                            <?= htmlspecialchars($dog['breed'] ?: 'Unknown Breed') ?>
                        </div>

                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Color:</span>
                            <?= htmlspecialchars($dog['color'] ?: 'Unknown') ?>
                        </div>
                        
                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Size:</span>
                            <?= ucfirst(htmlspecialchars($dog['size'] ?: 'Unknown')) ?>
                        </div>
                        
                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Gender:</span>
                            <?= ucfirst(htmlspecialchars($dog['gender'] ?: 'Unknown')) ?>
                        </div>
                        
                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Location Found:</span>
                            <?= htmlspecialchars($dog['location_found']) ?>
                        </div>
                        
                        <div class="dog-detail-item">
                            <span class="dog-detail-label">Date Caught:</span>
                            <?= date('F j, Y', strtotime($dog['date_caught'])) ?>
                        </div>

                        
                    </div>
                    <div class="dog-detail-item mt-4">
            <h5 class="dog-detail-label">Description</h5>
            <div class="description-text p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($dog['description'] ?: 'No description provided')) ?>
            </div>
        </div>
                </div>
                
                <div class="mt-4 text-center action-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($dog['status'] == 'for_claiming'): ?>
                            <?php
                            // Check if there's a pending or approved claim for this dog
                            $hasPendingClaim = isset($pending_claims[$dog['id']]);
                            $userHasClaim = isset($user_claims[$dog['id']]);
                            $isDisabled = $hasPendingClaim;
                            ?>
                            
                            <?php if ($isDisabled): ?>
                                <button class="btn btn-action btn-claim me-2" disabled style="opacity: 0.7; cursor: not-allowed;">
                                    <i class="fas fa-clock"></i> 
                                    <?= $userHasClaim ? 'Your Claim Pending' : 'Claim Pending' ?>
                                </button>
                            <?php else: ?>
                                <a href="claiming_form.php?dog_id=<?= $dog['id'] ?>" class="btn btn-action btn-claim me-2">
                                    <i class="fas fa-hand-holding-heart"></i> Claim This Dog
                                </a>
                            <?php endif; ?>
                        <?php elseif ($dog['status'] == 'for_adoption'): ?>
                            <?php
                            // Check if there's a pending or approved adoption for this dog
                            $hasPendingAdoption = isset($pending_adoptions[$dog['id']]);
                            $userHasAdoption = isset($user_adoptions[$dog['id']]);
                            $isDisabled = $hasPendingAdoption;
                            ?>
                            
                            <?php if ($isDisabled): ?>
                                <button class="btn btn-action btn-claim me-2" disabled style="opacity: 0.7; cursor: not-allowed;">
                                    <i class="fas fa-clock"></i> 
                                    <?= $userHasAdoption ? 'Your Adoption Pending' : 'Adoption Pending' ?>
                                </button>
                            <?php else: ?>
                                <a href="adoption_form.php?dog_id=<?= $dog['id'] ?>" class="btn btn-action btn-claim me-2">
                                    <i class="fas fa-home"></i> Adopt This Dog
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($isAdmin && in_array($dog['status'], $allowedStatusForDelete)): ?>
                        <button type="button" class="btn btn-action btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">
                            <i class="fas fa-trash"></i> Delete Record
                        </button>
                    <?php endif; ?>
                    
                    <a href="javascript:history.back()" class="btn btn-action btn-back">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade delete-confirm-modal" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! Are you absolutely sure you want to delete this dog record?
                        </div>
                        <div class="mb-3">
                            <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                            <input type="text" class="form-control" id="confirmDelete" name="confirm_delete" required>
                        </div>
                        <input type="hidden" name="delete_dog" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="submitDelete" disabled>Delete Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Success Delete Modal -->
    <div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" <?= $deleted ? 'data-bs-show="true"' : '' ?>>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Record Deleted</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Dog Record Deleted Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterDelete">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Enable/disable delete button based on confirmation text
        $('#confirmDelete').on('input', function() {
            $('#submitDelete').prop('disabled', $(this).val().toUpperCase() !== 'DELETE');
        });

        // Handle continue button after deletion
        $('#continueAfterDelete').click(function() {
            <?php if (isset($_SESSION['animal_admin_id'])): ?>
                window.location.href = '/mayor/animal/admin/management.php';
            <?php elseif (isset($_SESSION['pound_admin_id'])): ?>
                window.location.href = '/mayor/animal/pound_admin/management.php';
            <?php endif; ?>
        });

        // Show success modal if deleted
        <?php if ($deleted): ?>
            var successModal = new bootstrap.Modal(document.getElementById('successDeleteModal'));
            successModal.show();
        <?php endif; ?>
    });
    </script>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>