<?php
session_start();
include("includes/db_connect.php");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Store this page as the return URL for edits/views
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

// Fetch all parkings for this user
$stmt = $conn->prepare("SELECT * FROM parkings WHERE owner_id=? ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="de">
<?php
    $pageTitle = "Meine Parkplätze";
    include("includes/header.php");
?>

<style>
    .card-img-top {
        width: 100%;
        height: 180px;
        object-fit: cover;
    }
</style>
<body>

<div class="container mt-5">
    <h2>Meine Parkplätze</h2>
    <div class="row mt-4">
        <?php while($row = $result->fetch_assoc()):
            $imageDir = "uploads/parkings/" . $row['id'] . "/";
            $images = glob($imageDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
            sort($images); // ensure first image is first
            $mainImage = $images[0] ?? null;
            ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <?php if ($mainImage): ?>
                        <img src="<?= htmlspecialchars($mainImage) ?>" class="card-img-top" alt="Parking Image">
                    <?php else: ?>
                        <div class="card-img-top d-flex justify-content-center align-items-center bg-light text-muted">
                            No Image
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 80, "...")) ?></p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                        <p><strong>Price:</strong> €<?= number_format($row['price'], 2) ?></p>
                        <p><strong>Status:</strong>
                            <?php
                            switch ($row['status']) {
                                case 'approved': echo '<span class="text-success">Approved</span>'; break;
                                case 'pending': echo '<span class="text-warning">Pending</span>'; break;
                                case 'rejected': echo '<span class="text-danger">Rejected</span>'; break;
                                default: echo htmlspecialchars($row['status']);
                            }
                            ?>
                        </p>
                        <a href="parking.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">View</a>
                        <a href="parking_edit.php?id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
