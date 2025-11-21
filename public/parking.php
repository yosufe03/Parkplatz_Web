<?php
include("includes/db_connect.php");

$id = $_GET['id'] ?? 0;

// Fetch parking info with owner
$stmt = $conn->prepare("SELECT p.*, u.username AS owner_name
                        FROM parkings p
                        LEFT JOIN users u ON p.owner_id = u.id
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Parking space not found.");
}

$parking = $result->fetch_assoc();

// Get all images for this parking
$imageDir = "uploads/parkings/" . $parking['id'] . "/";

// Scan for multiple extensions
$images = glob($imageDir . "*.{jpg,jpeg,png}", GLOB_BRACE);

// Sort by filename (1.jpg, 2.png, etc.)
sort($images);

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($parking['title']) ?> - ParkShare</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .carousel-item img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }
    </style>
</head>

<body>
<?php include("includes/header.php"); ?>

<div class="container mt-4">

    <a href="javascript:history.back()" class="btn btn-outline-secondary mb-3">← Back</a>

    <div class="row">
        <div class="col-md-7">
            <?php if (!empty($images)): ?>
                <div id="parkingCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($images as $index => $img): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($img) ?>" alt="Parking Image <?= $index + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#parkingCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#parkingCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="bg-light text-center d-flex justify-content-center align-items-center" style="height:400px;">
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
