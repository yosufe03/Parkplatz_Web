<?php
// Include header FIRST to start session
$pageTitle = "Meine Favoriten";
include("includes/header.php");

// NOW check auth - session is started
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT p.* FROM parkings p JOIN favorites f ON f.parking_id = p.id WHERE f.user_id = ? ORDER BY f.created_at DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$parkings = [];
while ($r = $res->fetch_assoc()) $parkings[] = $r;
$stmt->close();

?>
<!DOCTYPE html>
<html lang="de">
<body>
<div class="container mt-4">
    <h2>Meine Favoriten</h2>
    <?php if (empty($parkings)): ?>
        <div class="alert alert-info mt-3">Du hast noch keine Favoriten.</div>
    <?php else: ?>
        <div class="row mt-3">
            <?php foreach ($parkings as $row): ?>
                <?php $parkingId = (int)$row['id'];
                    $mainImage = null;
                    if (!empty($row['main_image'])) {
                        $candidate = "uploads/parkings/$parkingId/" . $row['main_image'];
                        if (file_exists($candidate)) $mainImage = $candidate;
                    }
                    if (!$mainImage) {
                        $imgs = glob("uploads/parkings/$parkingId/*.{jpg,jpeg,png}", GLOB_BRACE);
                        sort($imgs);
                        if (!empty($imgs)) $mainImage = $imgs[0];
                    }
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <?php if ($mainImage): ?>
                            <img src="<?= htmlspecialchars($mainImage) ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                        <?php else: ?>
                            <div class="card-img-top d-flex justify-content-center align-items-center bg-light text-muted" style="height:180px;">No Image</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars(mb_strimwidth($row['description'],0,100,'...')) ?></p>
                            <p><strong>Preis:</strong> €<?= number_format((float)$row['price'],2) ?> / Tag</p>
                            <div class="d-flex gap-2">
                                <a href="parking.php?id=<?= $parkingId ?>" class="btn btn-sm btn-primary">Ansehen</a>
                                <form method="POST" action="favorite_toggle.php" style="display:inline;">
                                    <input type="hidden" name="parking_id" value="<?= $parkingId ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button class="btn btn-sm btn-outline-danger">Entfernen</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
