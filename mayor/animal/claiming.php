<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

$pageTitle = "Lost Dog Claims";
include '../../includes/header.php';

// Get dogs available for claiming or claimed
$dogs = $conn->query("
    SELECT d.id, d.breed, d.color, d.location_found, d.image_path, d.date_caught, d.status
    FROM dogs d
    WHERE d.status = 'for_claiming' OR d.status = 'claimed'
    ORDER BY FIELD(d.status, 'for_claiming', 'claimed'), d.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get user's claims and all pending/approved claims
$user_id = $_SESSION['user_id'];
$user_claims = [];
$pending_claims = [];

// Fetch user's claims
$user_claims_result = $conn->query("
    SELECT dog_id, status FROM dog_claims 
    WHERE user_id = $user_id 
    AND (status = 'pending' OR status = 'approved')
");

while ($row = $user_claims_result->fetch_assoc()) {
    $user_claims[$row['dog_id']] = $row['status'];
}

// Fetch all pending/approved claims (for admin view or to show status to all users)
$all_pending_result = $conn->query("
    SELECT dog_id, status FROM dog_claims 
    WHERE status = 'pending' OR status = 'approved'
");

while ($row = $all_pending_result->fetch_assoc()) {
    $pending_claims[$row['dog_id']] = $row['status'];
}

// Group dogs by status
$groupedDogs = [];
foreach ($dogs as $dog) {
    $status = $dog['status'];
    if (!isset($groupedDogs[$status])) {
        $groupedDogs[$status] = [];
    }
    $groupedDogs[$status][] = $dog;
}

// Human-readable status titles
$statusTitles = [
    'for_claiming' => 'For Claiming',
    'claimed' => 'Claimed'
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
        
        .status-section {
            margin-bottom: 1rem;
        }
        
        .status-header {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            margin-bottom: 0.5rem;
        }
        
        .status-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .status-count {
            background-color: var(--munici-green);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        
        .toggle-icon {
            transition: transform 0.2s ease;
            margin-right: 10px;
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
        }
        
        .btn-view, .btn-claim {
            padding: 8px 15px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .btn-view {
            background-color: #f0f0f0;
            color: var(--dark-color);
            border: 1px solid #ddd;
        }
        
        .btn-view:hover {
            background-color: #e0e0e0;
        }
        
        .btn-claim {
            background-color: var(--munici-green);
            color: white;
            border: none;
        }
        
        .btn-claim:hover {
            background-color: #0E3B85;
            color: white;
        }

        .btn-claim:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        .btn-claim:disabled:hover {
            background-color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dog-card-footer {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-view, .btn-claim {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">Lost Dog Claims</h1>
                    <p class="mb-0">Browse reported lost dogs and file a claim if one belongs to you</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if (empty($dogs)): ?>
    <div class="alert alert-info text-center">
        No dogs available for claiming at the moment.
    </div>
<?php else: ?>
    <?php foreach ($groupedDogs as $status => $dogsInGroup): ?>
        <div class="status-section">
            <div class="status-header" onclick="toggleSection('<?= $status ?>')">
                <i class="fas fa-chevron-down toggle-icon" id="icon-<?= $status ?>"></i>
                <h2 class="status-title">
                    <?= $statusTitles[$status] ?>
                    <span class="status-count"><?= count($dogsInGroup) ?></span>
                </h2>
            </div>
            
            <div class="section-content" id="section-<?= $status ?>">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-2">
                    <?php foreach ($dogsInGroup as $dog): ?>
                        <div class="col">
                            <div class="dog-card card">
                                <div class="dog-image-container">
                                    <img src="<?= '/' . htmlspecialchars($dog['image_path'] ?? 'assets/default-dog.jpg') ?>"
                                         class="dog-card-img" 
                                         alt="Dog image">
                                </div>
                                <div class="dog-card-body">
                                    <h5 class="dog-card-title">
                                        Dog ID: <?= $dog['id'] ?>
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
                                    <a href="view_dog.php?id=<?= $dog['id'] ?>" class="btn btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($status === 'claimed'): ?>
                                        <button class="btn btn-claim" disabled style="opacity: 0.7; cursor: not-allowed;">
                                            <i class="fas fa-check-circle"></i> Claimed
                                        </button>
                                    <?php else: ?>
                                        <?php
                                        // Check if there's a pending or approved claim for this dog
                                        $hasPendingClaim = isset($pending_claims[$dog['id']]);
                                        $userHasClaim = isset($user_claims[$dog['id']]);
                                        $isDisabled = $hasPendingClaim;
                                        ?>
                                        
                                        <?php if ($isDisabled): ?>
                                            <button class="btn btn-claim" disabled style="opacity: 0.7; cursor: not-allowed;">
                                                <i class="fas fa-clock"></i> 
                                                <?= $userHasClaim ? 'Your Claim Pending' : 'Claim Pending' ?>
                                            </button>
                                        <?php else: ?>
                                            <a href="claiming_form.php?dog_id=<?= $dog['id'] ?>" class="btn btn-claim">
                                                <i class="fas fa-hand-holding-heart"></i> Claim
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleSection(status) {
            const section = document.getElementById(`section-${status}`);
            const icon = document.getElementById(`icon-${status}`);
            
            if (section.style.display === 'none') {
                section.style.display = 'block';
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
            } else {
                section.style.display = 'none';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            }
        }
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>