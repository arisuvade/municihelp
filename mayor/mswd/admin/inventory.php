<?php
session_start();
include '../../../includes/db.php';

if (!isset($_SESSION['mswd_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$mswd_admin_id = $_SESSION['mswd_admin_id'];
$admin_query = $conn->query("SELECT name FROM admins WHERE id = $mswd_admin_id");
$admin_data = $admin_query->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

// Handle form submission to update equipment quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_equipment'])) {
        $equipment_id = $_POST['equipment_id'];
        $available_quantity = (int)$_POST['available_quantity'];
        
        // Validate quantity
        if ($available_quantity >= 0) {
            $stmt = $conn->prepare("UPDATE equipment_inventory SET available_quantity = ? WHERE equipment_type_id = ?");
            $stmt->bind_param("ii", $available_quantity, $equipment_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Equipment quantity updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating equipment quantity: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Quantity cannot be negative!";
        }
    }
    elseif (isset($_POST['add_equipment'])) {
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        
        if (!empty($name) && $quantity >= 0) {
            // First check if equipment already exists
            $check_query = $conn->query("SELECT id FROM mswd_types WHERE name = '$name' AND parent_id = 8");
            
            if ($check_query->num_rows > 0) {
                $_SESSION['error_message'] = "Equipment with this name already exists!";
            } else {
                // Insert into mswd_types
                $conn->query("INSERT INTO mswd_types (name, parent_id, is_online) VALUES ('$name', 8, 1)");
                $new_equipment_id = $conn->insert_id;
                
                // Insert into equipment_inventory
                $conn->query("INSERT INTO equipment_inventory (equipment_type_id, available_quantity) VALUES ($new_equipment_id, $quantity)");
                
                // Add default requirements
                $default_requirements = [
                    'Barangay Indigency',
                    'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',
                    'Valid ID'
                ];
                
                // Add custom requirements (up to 6 total)
                for ($i = 1; $i <= 3; $i++) {
                    $requirement = trim($conn->real_escape_string($_POST["requirement_$i"] ?? ''));
                    if (!empty($requirement)) {
                        $default_requirements[] = $requirement;
                    }
                }
                
                foreach ($default_requirements as $requirement) {
                    if (!empty($requirement)) {
                        $conn->query("INSERT INTO mswd_types_requirements (name, mswd_types_id) VALUES ('$requirement', $new_equipment_id)");
                    }
                }
                
                $_SESSION['success_message'] = "Equipment added successfully with requirements!";
            }
        } else {
            $_SESSION['error_message'] = "Please provide valid equipment name and quantity!";
        }
    }
    elseif (isset($_POST['delete_equipment'])) {
        $equipment_id = intval($_POST['equipment_id']);
        $confirmation = $conn->real_escape_string($_POST['confirmation'] ?? '');
        
        if (strtoupper($confirmation) !== 'DELETE') {
            $_SESSION['error_message'] = "Please type 'DELETE' to confirm deletion!";
        } else {
            // Check if equipment exists
            $check_query = $conn->query("SELECT id FROM mswd_types WHERE id = $equipment_id AND parent_id = 8");
            
            if ($check_query->num_rows > 0) {
                // Delete from equipment_inventory first
                $conn->query("DELETE FROM equipment_inventory WHERE equipment_type_id = $equipment_id");
                
                // Delete from requirements
                $conn->query("DELETE FROM mswd_types_requirements WHERE mswd_types_id = $equipment_id");
                
                // Then delete from mswd_types
                $conn->query("DELETE FROM mswd_types WHERE id = $equipment_id");
                
                $_SESSION['success_message'] = "Equipment and all associated requirements deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Equipment not found!";
            }
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: inventory.php");
    exit();
}

// Get equipment availability - sorted by ID (lowest to highest)
$equipment_availability = $conn->query("
    SELECT t.id, t.name, ei.available_quantity 
    FROM mswd_types t 
    LEFT JOIN equipment_inventory ei ON t.id = ei.equipment_type_id 
    WHERE t.parent_id = 8
    ORDER BY t.id ASC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Equipment Inventory';
include '../../../includes/header.php';

// Get messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// Clear messages from session
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
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
            filter: brightness(0) invert(1);
        }
        
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .inventory-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .inventory-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.2rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .table-responsive {
            border-radius: 0 0 10px 10px;
            overflow-x: auto;
            width: 100%;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
            padding: 12px;
        }
        
        .inventory-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .inventory-table tr:last-child td {
            border-bottom: none;
        }
        
        .inventory-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-update {
            background-color: var(--approved);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-update:hover {
            background-color: #218838;
            color: white;
        }
        
        .btn-delete {
            background-color: var(--declined);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }
        
        .btn-add {
            background-color: var(--munici-green);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-add:hover {
            background-color: #0E3B85;
            color: white;
        }
        
        /* Column widths */
        .table-col-seq {
            width: 5%;
            text-align: center;
        }
        
        .table-col-equipment {
            width: 60%;
            text-align: left;
        }
        
        .table-col-quantity {
            width: 20%;
            text-align: center;
        }
        
        .table-col-actions {
            width: 15%;
            text-align: center;
        }
        
        /* Delete confirmation input */
        .delete-confirm {
            width: 100%;
            text-align: center;
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
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
            
            .inventory-table {
                font-size: 0.9rem;
            }
            
            .inventory-table th, 
            .inventory-table td {
                padding: 8px;
            }
            
            .quantity-input {
                width: 60px;
            }
            
            .table-col-equipment {
                width: 40%;
            }
            
            .table-col-quantity {
                width: 25%;
            }
            
            .table-col-actions {
                width: 35%;
            }
            
            .action-text {
                display: none;
            }
            
            .btn i {
                margin-right: 0 !important;
            }
        }
        
        @media (min-width: 769px) {
            .action-text {
                display: inline;
            }
            
            .btn i {
                margin-right: 5px;
            }
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Requirements styling */
        .requirement-input {
            margin-bottom: 10px;
        }
        
        .requirements-container {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Equipment Inventory</h1>
                    <p class="mb-0">Manage equipment availability and quantities</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                    <span><?= htmlspecialchars($admin_name) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Action Header -->
        <div class="action-header">
            <h2 class="mb-0">Equipments List</h2>
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                <i class="fas fa-plus"></i> <span class="action-text">Add Equipment</span>
            </button>
        </div>

        <!-- Error Messages -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Equipment Inventory Table -->
        <div class="inventory-card card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th class="table-col-seq">#</th>
                                <th class="table-col-equipment">Name</th>
                                <th class="table-col-quantity">Available Quantity</th>
                                <th class="table-col-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sequence_number = 1;
                            foreach ($equipment_availability as $equipment): 
                            ?>
                                <tr>
                                    <td class="table-col-seq"><?= $sequence_number ?></td>
                                    <td class="table-col-equipment"><?= htmlspecialchars($equipment['name']) ?></td>
                                    <td class="table-col-quantity">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="equipment_id" value="<?= $equipment['id'] ?>">
                                            <input type="number" name="available_quantity" 
                                                   value="<?= $equipment['available_quantity'] ?? 0 ?>" 
                                                   min="0" class="quantity-input" required>
                                    </td>
                                    <td class="table-col-actions">
                                        <div class="action-buttons">
                                            <button type="submit" name="update_equipment" class="btn-update">
                                                <i class="fas fa-sync-alt"></i> <span class="action-text">Update</span>
                                            </button>
                                        </form>
                                        <button type="button" class="btn-delete" data-bs-toggle="modal" data-bs-target="#deleteEquipmentModal" data-id="<?= $equipment['id'] ?>">
                                            <i class="fas fa-trash"></i> <span class="action-text">Delete</span>
                                        </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                            $sequence_number++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEquipmentModalLabel">Add Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Equipment Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Initial Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="0" required>
                        </div>
                        
                        <div class="requirements-container">
                            <h6>Additional Requirements (Optional)</h6>
                            <p class="text-muted small">Add up to 6 additional requirements beyond the default ones</p>
                            
                            <div class="mb-2 requirement-input">
                                <label class="form-label">Requirement 1</label>
                                <input type="text" class="form-control" name="requirement_1" placeholder="Optional requirement">
                            </div>
                            
                            <div class="mb-2 requirement-input">
                                <label class="form-label">Requirement 2</label>
                                <input type="text" class="form-control" name="requirement_2" placeholder="Optional requirement">
                            </div>
                            
                            <div class="mb-2 requirement-input">
                                <label class="form-label">Requirement 3</label>
                                <input type="text" class="form-control" name="requirement_3" placeholder="Optional requirement">
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> This equipment will automatically include these default requirements:
                            <ul class="mb-0 mt-2">
                                <li>Barangay Indigency</li>
                                <li>Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)</li>
                                <li>Valid ID</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_equipment">Add Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Equipment Modal -->
    <div class="modal fade" id="deleteEquipmentModal" tabindex="-1" aria-labelledby="deleteEquipmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="equipment_id" id="delete_equipment_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteEquipmentModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! This will delete the equipment and all its requirements.
                        </div>
                        <div class="mb-3">
                            <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                            <input type="text" class="form-control delete-confirm" id="confirmDelete" name="confirmation" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_equipment" id="submitDelete" disabled>Delete Equipment</button>
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
                    <h4 class="mb-3">Success!</h4>
                    <p class="mb-4" id="successMessage"></p>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle text-danger" style="font-size: 5rem;"></i>
                    </div>
                    <h4 class="mb-3">Error</h4>
                    <p class="mb-4" id="errorMessage"></p>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Validate quantity inputs
        $('input[type="number"]').on('input', function() {
            if ($(this).val() < 0) {
                $(this).val(0);
            }
        });

        // Show success modal if there's a success message
        <?php if ($success_message): ?>
            $('#successMessage').text('<?= addslashes($success_message) ?>');
            $('#successModal').modal('show');
        <?php endif; ?>

        // Show error modal if there's an error message
        <?php if ($error_message): ?>
            $('#errorMessage').text('<?= addslashes($error_message) ?>');
            $('#errorModal').modal('show');
        <?php endif; ?>
        
        // Delete confirmation validation
        $('#confirmDelete').on('input', function() {
            $('#submitDelete').prop('disabled', $(this).val().toUpperCase() !== 'DELETE');
        });
        
        // Set equipment ID when delete button is clicked
        $('#deleteEquipmentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var equipmentId = button.data('id');
            $('#delete_equipment_id').val(equipmentId);
            $('#confirmDelete').val(''); // Clear previous input
            $('#submitDelete').prop('disabled', true);
        });
        
        // Check screen size on load and resize
        function checkScreenSize() {
            if ($(window).width() < 576) {
                $('.action-text').hide();
                $('.btn i').css('margin-right', '0');
            } else {
                $('.action-text').show();
                $('.btn i').css('margin-right', '5px');
            }
        }
        
        checkScreenSize();
        $(window).resize(checkScreenSize);
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>