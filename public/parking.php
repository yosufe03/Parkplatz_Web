<?php
include("includes/db_connect.php");
session_start();

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT p.*, u.username AS owner_name
                        FROM parkings p
                        LEFT JOIN users u ON p.owner_id = u.id
                        WHERE p.id = ? AND p.status='approved'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Parking space not found.");
}

$parking = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($parking['title']) ?> - ParkShare</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>

<body>

<?php include("includes/header.php"); ?>

<div class="container mt-4">

    <a href="javascript:history.back()" class="btn btn-outline-secondary mb-3">← Back</a>

    <div class="row">
        <div class="col-md-7">
            <?php if ($parking['main_image']): ?>
                <img src="uploads/<?= htmlspecialchars($parking['main_image']) ?>" class="main-image">
            <?php else: ?>
                <div class="main-image d-flex justify-content-center align-items-center bg-light text-muted">
                    No Image Available
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-5">
            <h2><?= htmlspecialchars($parking['title']) ?></h2>

            <p class="text-muted mb-2">
                📍 <strong><?= htmlspecialchars($parking['location']) ?></strong>
            </p>

            <h4 class="text-primary mb-3">
                €<?= number_format($parking['price'], 2) ?> / day
            </h4>

            <?php if (!empty($parking['owner_name'])): ?>
                <p><strong>Owner:</strong> <?= htmlspecialchars($parking['owner_name']) ?></p>
            <?php endif; ?>

            <hr>

            <h5>Description</h5>
            <p><?= nl2br(htmlspecialchars($parking['description'])) ?></p>
        </div>
    </div>

</div>


</body>
</html>
