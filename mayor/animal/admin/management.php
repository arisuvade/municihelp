<?php
session_start();
include '../../../includes/db.php';

// Check for admin session
if (!isset($_SESSION['animal_admin_id'])) {
    header("Location: ../../../includes/auth/login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['animal_admin_id'];
$admin_type = 'animal';

$admin_query = $conn->prepare("SELECT name FROM admins WHERE id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();

if (!$admin_result) {
    die("Database error: " . $conn->error);
}
$admin_data = $admin_result->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

$pageTitle = 'Dog Management';

// Handle form submissions FIRST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_dog'])) {
        // Add new dog
        $breed = $conn->real_escape_string($_POST['breed'] ?? '');
        $color = $conn->real_escape_string($_POST['color'] ?? '');
        $size = $conn->real_escape_string($_POST['size'] ?? '');
        $gender = $conn->real_escape_string($_POST['gender'] ?? '');
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $location_found = $conn->real_escape_string($_POST['location_found'] ?? '');
        $date_caught = $_POST['date_caught'] ?? date('Y-m-d H:i:s');
        $status = 'for_claiming';
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../../uploads/mayor/animal/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'dog_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = 'uploads/mayor/animal/' . $filename;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO dogs (breed, color, size, gender, description, location_found, date_caught, image_path, status, createdby_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssi", $breed, $color, $size, $gender, $description, $location_found, $date_caught, $image_path, $status, $admin_id);
        
        if ($stmt->execute()) {
            header("Location: management.php?created=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error adding dog: ' . $conn->error;
            header("Location: management.php");
            exit();
        }
    }
    elseif (isset($_POST['update_status'])) {
        // Handle status update
        $dog_id = intval($_POST['dog_id']);
        $new_status = $conn->real_escape_string($_POST['new_status']);
        
        $update_stmt = $conn->prepare("UPDATE dogs SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $dog_id);
        
        if ($update_stmt->execute()) {
            header("Location: management.php?status_updated=1");
            exit();
        } else {
            $_SESSION['error_message'] = 'Error updating dog status: ' . $conn->error;
            header("Location: management.php");
            exit();
        }
    }
}

// Now include headers after all potential redirects
include '../../../includes/header.php';

// Get filter values from GET parameters
$searchTerm = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$genderFilter = isset($_GET['gender']) ? $conn->real_escape_string($_GET['gender']) : '';
$sizeFilter = isset($_GET['size']) ? $conn->real_escape_string($_GET['size']) : '';
$dateFilter = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';

// Build the SQL query with filters
$query = "SELECT id, breed, color, size, gender, location_found, date_caught, image_path, status FROM dogs WHERE 1=1";
$params = [];
$types = '';

// Add search filter
if (!empty($searchTerm)) {
    $query .= " AND (breed LIKE ? OR color LIKE ? OR location_found LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= 'sss';
}

// Add status filter
if (!empty($statusFilter)) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

// Add gender filter
if (!empty($genderFilter)) {
    $query .= " AND gender = ?";
    $params[] = $genderFilter;
    $types .= 's';
}

// Add size filter
if (!empty($sizeFilter)) {
    $query .= " AND size = ?";
    $params[] = $sizeFilter;
    $types .= 's';
}

// Add date filter
if (!empty($dateFilter)) {
    $query .= " AND DATE(date_caught) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";

// Prepare and execute the query with filters
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$dogs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Human-readable status titles
$statusTitles = [
    'for_claiming' => 'For Claiming',
    'claimed' => 'Claimed',
    'for_adoption' => 'For Adoption',
    'adopted' => 'Adopted',
    'euthanized' => 'Euthanized',
    'false_report' => 'False Report'
];
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
        
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .filter-card {
            margin-bottom: 1.5rem;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .dog-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            transition: transform 0.3s;
            height: 100%;
            background-color: white;
        }
        
        .dog-card:hover {
            transform: translateY(-5px);
        }
        
        .dog-image-container {
            width: 100%;
            padding-top: 75%;
            position: relative;
            overflow: hidden;
            border-radius: 10px 10px 0 0;
        }
        
        .dog-card-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dog-card-body {
            padding: 1rem;
        }
        
        .dog-card-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .dog-card-text {
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .dog-card-footer {
            background-color: white;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem;
            border-radius: 0 0 10px 10px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn-view, .btn-add, .btn-status {
            padding: 8px 15px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-view {
            background-color: #f0f0f0;
            color: var(--dark-color);
            border: 1px solid #ddd;
        }
        
        .btn-view:hover {
            background-color: #e0e0e0;
        }
        
        .btn-add {
            background-color: var(--munici-green);
            color: white;
            border: none;
        }
        
        .btn-add:hover {
            background-color: #0E3B85;
            color: white;
        }

        .btn-status {
            background-color: #17A2B8;
            color: white;
            border: none;
        }
        
        .btn-status:hover {
            background-color: #138496;
        }

        .btn-euthanize {
            background-color: var(--cancelled);
            color: white;
            border: none;
        }
        
        .btn-euthanize:hover {
            background-color: #5a6268;
        }

        /* Status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: capitalize;
            font-size: 0.8rem;
            display: inline-block;
            margin-left: 8px;
        }
        
        .status-for_claiming {
            background-color: var(--pending);
            color: #000;
        }
        
        .status-for_adoption {
            background-color: #17A2B8;
            color: #FFF;
        }
        
        .status-claimed {
            background-color: var(--approved);
            color: #FFF;
        }
        
        .status-adopted {
            background-color: var(--completed);
            color: #FFF;
        }
        
        .status-euthanized {
            background-color: var(--cancelled);
            color: #FFF;
        }
        
        .status-pending_claim, .status-pending_adoption {
            background-color: #FFC107;
            color: #000;
        }
        
        /* Modal styles */
        .modal-header {
            background-color: var(--munici-green-light);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .delete-confirm-modal .modal-header {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .form-label.required:after {
            content: " *";
            color: var(--declined);
        }
        
        .no-dogs {
            text-align: center;
            padding: 3rem;
            color: #666;
            margin-top: 2rem;
        }
        
        .no-dogs i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--munici-green);
        }
        
        .no-dogs h4 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .no-dogs p {
            font-size: 1rem;
            color: #6c757d;
        }
        
        /* Mobile specific styles */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dog-card-footer {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-view, .btn-status {
                width: 100%;
            }
            
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Dog Management</h1>
                    <p class="mb-0">Manage all dogs in the system</p>
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
            <h2 class="mb-0">Dogs List</h2>
            <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addDogModal">
                <i class="fas fa-plus"></i> Add Dog
            </button>
        </div>

        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchFilter" 
                            placeholder="Search by ID or location" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="for_claiming" <?= (isset($_GET['status']) && $_GET['status'] === 'for_claiming') ? 'selected' : '' ?>>For Claiming</option>
                            <option value="claimed" <?= (isset($_GET['status']) && $_GET['status'] === 'claimed') ? 'selected' : '' ?>>Claimed</option>
                            <option value="for_adoption" <?= (isset($_GET['status']) && $_GET['status'] === 'for_adoption') ? 'selected' : '' ?>>For Adoption</option>
                            <option value="adopted" <?= (isset($_GET['status']) && $_GET['status'] === 'adopted') ? 'selected' : '' ?>>Adopted</option>
                            <option value="euthanized" <?= (isset($_GET['status']) && $_GET['status'] === 'euthanized') ? 'selected' : '' ?>>Euthanized</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Gender</label>
                        <select class="form-select" id="genderFilter">
                            <option value="">All Genders</option>
                            <option value="male" <?= (isset($_GET['gender']) && $_GET['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= (isset($_GET['gender']) && $_GET['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Size</label>
                        <select class="form-select" id="sizeFilter">
                            <option value="">All Sizes</option>
                            <option value="small" <?= (isset($_GET['size']) && $_GET['size'] === 'small') ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= (isset($_GET['size']) && $_GET['size'] === 'medium') ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= (isset($_GET['size']) && $_GET['size'] === 'large') ? 'selected' : '' ?>>Large</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Date Caught</label>
                        <input type="date" class="form-control" id="dateFilter" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Dogs List -->
        <div id="resultsContainer">
            <?php if (empty($dogs)): ?>
                <div class="no-dogs">
                    <i class="fas fa-dog"></i>
                    <h4>No Dogs Found</h4>
                    <p>There are no dogs to display.</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($dogs as $dog): ?>
                        <div class="col">
                            <div class="dog-card card">
                                <div class="dog-image-container">
                                    <img src="<?= '/' . htmlspecialchars($dog['image_path'] ?? '../../assets/default-dog.jpg') ?>"
                                         class="dog-card-img" 
                                         alt="Dog image">
                                </div>
                                <div class="dog-card-body">
                                    <h5 class="dog-card-title">
                                        Dog ID: <?= $dog['id'] ?>
                                        <span class="status-badge status-<?= $dog['status'] ?>">
                                            <?= str_replace('_', ' ', $dog['status']) ?>
                                        </span>
                                    </h5>
                                    <p class="dog-card-text">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?= htmlspecialchars($dog['location_found']) ?>
                                    </p>
                                    <p class="dog-card-text">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?= date('M d, Y', strtotime($dog['date_caught'])) ?>
                                    </p>
                                </div>
                                <div class="dog-card-footer">
                                    <a href="../../../mayor/animal/view_dog.php?id=<?= $dog['id'] ?>" class="btn btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <!-- Status change buttons -->
                                    <?php if ($dog['status'] === 'for_claiming'): ?>
                                        <button class="btn btn-status btn-mark-adoption" data-dog-id="<?= $dog['id'] ?>">
                                            <i class="fas fa-paw"></i> Mark for Adoption
                                        </button>
                                        
                                        <button class="btn btn-euthanize" data-dog-id="<?= $dog['id'] ?>">
                                            <i class="fas fa-heartbeat"></i> Euthanize
                                        </button>
                                    <?php elseif ($dog['status'] === 'for_adoption'): ?>
                                        <button class="btn btn-status btn-revert-claiming" data-dog-id="<?= $dog['id'] ?>">
                                            <i class="fas fa-undo"></i> Revert to Claiming
                                        </button>
                                        
                                        <button class="btn btn-euthanize" data-dog-id="<?= $dog['id'] ?>">
                                            <i class="fas fa-heartbeat"></i> Euthanize
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Dog Modal -->
    <div class="modal fade" id="addDogModal" tabindex="-1" aria-labelledby="addDogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDogModalLabel">Add Dog</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="breed" class="form-label required">Breed</label>
                                <input type="text" class="form-control" id="breed" name="breed" required>
                            </div>
                            <div class="col-md-6">
                                <label for="color" class="form-label required">Color</label>
                                <input type="text" class="form-control" id="color" name="color" required>
                            </div>
                            <div class="col-md-4">
                                <label for="size" class="form-label required">Size</label>
                                <select class="form-select" id="size" name="size" required>
                                    <option value="">Select size</option>
                                    <option value="small">Small</option>
                                    <option value="medium">Medium</option>
                                    <option value="large">Large</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="form-label required">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="date_caught" class="form-label required">Date Caught</label>
                                <input type="datetime-local" class="form-control" id="date_caught" name="date_caught" required>
                            </div>
                            <div class="col-12">
                                <label for="location_found" class="form-label required">Location Found</label>
                                <input type="text" class="form-control" id="location_found" name="location_found" required>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="image" class="form-label required">Dog Image</label>
                                <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
                                <div class="form-text">Recommended: Landscape orientation<br>
                                    Max file size: 5MB
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_dog">Add Dog</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Update Confirmation Modals -->
    <!-- For Adoption Confirmation Modal -->
    <div class="modal fade" id="forAdoptionConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="forAdoptionForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Mark for Adoption</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to mark this dog as available for adoption?</p>
                        <input type="hidden" name="dog_id" id="forAdoptionDogId">
                        <input type="hidden" name="new_status" value="for_adoption">
                        <input type="hidden" name="update_status" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark for Adoption</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Revert to Claiming Confirmation Modal -->
    <div class="modal fade" id="revertClaimingConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="revertClaimingForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Revert to Claiming</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to revert this dog to "For Claiming" status?</p>
                        <input type="hidden" name="dog_id" id="revertClaimingDogId">
                        <input type="hidden" name="new_status" value="for_claiming">
                        <input type="hidden" name="update_status" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Revert Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Euthanize Confirmation Modal -->
    <div class="modal fade" id="euthanizeConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="euthanizeForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Confirm Euthanasia</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is irreversible! Are you absolutely sure you want to mark this dog as euthanized?
                        </div>
                        <div class="mb-3">
                            <label for="confirmEuthanize" class="form-label">Type "EUTHANIZE" to confirm:</label>
                            <input type="text" class="form-control" id="confirmEuthanize" required>
                        </div>
                        <input type="hidden" name="dog_id" id="euthanizeDogId">
                        <input type="hidden" name="new_status" value="euthanized">
                        <input type="hidden" name="update_status" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="submitEuthanize" disabled>Confirm Euthanasia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Create Modal -->
    <div class="modal fade" id="successCreateModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Dog Added</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Dog Added Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterCreate">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Status Update Modal -->
    <div class="modal fade" id="successStatusModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Status Updated</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h4>Dog Status Updated Successfully!</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="continueAfterStatus">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set current date/time for new dog form
            const now = new Date();
            const timezoneOffset = now.getTimezoneOffset() * 60000;
            const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
            $('#date_caught').val(localISOTime);

            // Status change confirmation handlers
            $(document).on('click', '.btn-mark-adoption', function() {
                const dogId = $(this).data('dog-id');
                $('#forAdoptionDogId').val(dogId);
                $('#forAdoptionConfirmModal').modal('show');
            });

            $(document).on('click', '.btn-revert-claiming', function() {
                const dogId = $(this).data('dog-id');
                $('#revertClaimingDogId').val(dogId);
                $('#revertClaimingConfirmModal').modal('show');
            });

            $(document).on('click', '.btn-euthanize', function() {
                const dogId = $(this).data('dog-id');
                $('#euthanizeDogId').val(dogId);
                $('#confirmEuthanize').val(''); // Clear previous input
                $('#submitEuthanize').prop('disabled', true);
                $('#euthanizeConfirmModal').modal('show');
            });

            // Euthanize confirmation validation
            $('#confirmEuthanize').on('input', function() {
                $('#submitEuthanize').prop('disabled', $(this).val().toUpperCase() !== 'EUTHANIZE');
            });

            // Live search function
            $('#searchFilter').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                
                if (searchTerm.length === 0) {
                    // Show all dogs if search is empty
                    $('.col').show();
                    checkEmptyResults();
                    return;
                }
                
                let hasResults = false;
                
                $('.col').each(function() {
                    const cardText = $(this).text().toLowerCase();
                    if (cardText.includes(searchTerm)) {
                        $(this).show();
                        hasResults = true;
                    } else {
                        $(this).hide();
                    }
                });
                
                checkEmptyResults();
            });
            
            function checkEmptyResults() {
                const visibleDogs = $('.col:visible').length;
                if (visibleDogs === 0) {
                    $('.no-dogs').show();
                } else {
                    $('.no-dogs').hide();
                }
            }

            // Original filter function (for other filters)
            function applyFilters() {
                const statusFilter = $('#statusFilter').val();
                const genderFilter = $('#genderFilter').val();
                const sizeFilter = $('#sizeFilter').val();
                const dateFilter = $('#dateFilter').val();
                
                // Build URL with filter parameters
                let url = 'management.php?';
                if ($('#searchFilter').val()) url += `search=${encodeURIComponent($('#searchFilter').val())}&`;
                if (statusFilter) url += `status=${encodeURIComponent(statusFilter)}&`;
                if (genderFilter) url += `gender=${encodeURIComponent(genderFilter)}&`;
                if (sizeFilter) url += `size=${encodeURIComponent(sizeFilter)}&`;
                if (dateFilter) url += `date=${encodeURIComponent(dateFilter)}&`;
                
                // Remove trailing & if exists
                url = url.replace(/&$/, '');
                
                // Reload page with filters
                window.location.href = url;
            }
            
            // Apply filters when other filters change (still refreshes page)
            $('#statusFilter, #genderFilter, #sizeFilter, #dateFilter').on('change', function() {
                applyFilters();
            });

            // Check for success messages in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const successCreateModal = new bootstrap.Modal('#successCreateModal');
            const successStatusModal = new bootstrap.Modal('#successStatusModal');

            if (urlParams.has('created')) {
                successCreateModal.show();
            }
            else if (urlParams.has('status_updated')) {
                successStatusModal.show();
            }

            // Continue buttons for success modals
            $('#continueAfterCreate, #continueAfterStatus').click(function() {
                window.location.href = window.location.pathname; // Reload without query params
            });
        });
    </script>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>