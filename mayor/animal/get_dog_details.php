<?php
require_once '../../includes/db.php';

if (!isset($_GET['id'])) {
    die('Invalid request');
}

$dogId = (int)$_GET['id'];
$dog = $conn->query("
    SELECT d.breed, d.color, d.location_found, d.image_path
    FROM dogs d
    WHERE d.id = $dogId
")->fetch_assoc();

if (!$dog) {
    die('Dog not found');
}
?>

<div class="row">
    <div class="col-md-6">
        <img src="<?= '../../' . $dog['image_path'] ?>" class="modal-dog-img" alt="Dog image">
    </div>
    <div class="col-md-6">
        <div class="dog-detail-item">
            <span class="dog-detail-label">Breed:</span>
            <?= htmlspecialchars($dog['breed'] ?: 'Unknown') ?>
        </div>
        <div class="dog-detail-item">
            <span class="dog-detail-label">Color:</span>
            <?= htmlspecialchars($dog['color'] ?: 'Unknown') ?>
        </div>
        <div class="dog-detail-item">
            <span class="dog-detail-label">Location Found:</span>
            <?= htmlspecialchars($dog['location_found']) ?>
        </div>
    </div>
</div>