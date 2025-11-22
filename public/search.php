<?php
session_start();
include("includes/db_connect.php");

$location = $_GET['location'] ?? '';

$stmt = $conn->prepare("SELECT * FROM parkings WHERE location LIKE ? AND status='approved'");
$searchTerm = "%$location%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<?php
    $pageTitle = "Search Results";
    include("includes/header.php");
?>

<body>

<div class="container mt-5">
    <h2>Search Results for "<?= htmlspecialchars($location) ?>"</h2>
    <a href="dashboard.php" class="btn btn-outline-secondary mb-3">Back to Dashboard</a>

    <div class="row mt-3">
        <?php while($row = $result->fetch_assoc()):
            $parkingId = $row['id'];
            $mainImage = null;
            $imageDir = "uploads/parkings/$parkingId/";
            if (!empty($row['main_image']) && file_exists($imageDir . $row['main_image'])) {
                $mainImage = $imageDir . $row['main_image'];
            } else {
                // fallback: first image in folder
                $images = glob($imageDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
                sort($images);
                $mainImage = $images[0] ?? null;
            }
            ?>
            <div class="col-md-4 mb-3">
                <!-- Make entire card clickable -->
                <a href="parking.php?id=<?= $parkingId ?>" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm">

                        <?php if ($mainImage): ?>
                            <img src="<?= htmlspecialchars($mainImage) ?>"
                                 class="card-img-top"
                                 style="height: 180px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top d-flex justify-content-center align-items-center bg-light text-muted"
                                 style="height:180px;">
                                No Image
                            </div>
                        <?php endif; ?>

                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                            <p class="card-text">
                                <?= htmlspecialchars(mb_strimwidth($row['description'], 0, 100, "...")) ?>
                            </p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                            <p><strong>Price:</strong> €<?= number_format($row['price'], 2) ?></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
