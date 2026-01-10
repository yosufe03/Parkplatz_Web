<?php
include("includes/parking_utils.php");
include("includes/header.php");

$id = isset($_GET['id']) ? (int)trim($_GET['id']) : 0;
if ($id <= 0) die("Invalid parking id.");

$parking = get_parking_by_id($id);
if (!$parking) die("Parking not found");

$isOwner = isset($_SESSION['user_id']) && $parking['owner_id'] == $_SESSION['user_id'];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if ($parking['status'] === 'rejected' && !$isOwner && !$isAdmin) {
    die("Parkplatz nicht gefunden oder keine Berechtigung.");
}

$ratingData = get_parking_rating($id);
$reviews = get_parking_reviews($id);
$images = get_image_files($id);
$districtName = get_district_name($parking['district_id']);
$neighborhoodName = get_neighborhood_name($parking['neighborhood_id']);

$userReview = null;
$isFavorite = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $userReview = get_user_review($id, $uid);
    $isFavorite = is_parking_favorite($id, $uid);
}
?>

<!DOCTYPE html>
<html lang="de">
<body>

<div class="container mt-4">
    <div class="row">

        <!-- LEFT -->
        <div class="col-md-7">
            <?php if ($images): ?>
                <div id="carousel" class="carousel slide mb-4">
                    <div class="carousel-inner">
                        <?php foreach ($images as $i => $img): ?>
                            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($img) ?>" class="parking-img" alt="Parking image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php include("includes/calendar.php"); ?>
        </div>

        <!-- RIGHT -->
        <div class="col-md-5">
            <h2><?= htmlspecialchars($parking['title']) ?></h2>

            <p class="text-muted">
                <strong>Distrikt:</strong> <?= htmlspecialchars($districtName ?: '—') ?> <br>
                <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName ?: '—') ?>
            </p>

            <h4 class="text-primary">
                €<?= number_format((float)$parking['price'], 2) ?> / day
            </h4>

            <?php if (!empty($parking['owner_name'])): ?>
                <p><strong>Owner:</strong> <?= htmlspecialchars($parking['owner_name']) ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" action="favorite_toggle.php" class="mb-2">
                    <input type="hidden" name="parking_id" value="<?= $id ?>">
                    <?php if ($isFavorite): ?>
                        <input type="hidden" name="action" value="remove">
                        <button class="btn btn-sm btn-outline-danger">♥ Entfernen</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                        <button class="btn btn-sm btn-outline-primary">♡ Favorisieren</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <!-- Ratings summary -->
            <div class="mb-2">
                <strong>Rating:</strong>
                <?php if ($ratingData['review_count'] > 0): ?>
                    <span class="text-warning">★ <?= number_format($ratingData['avg_rating'], 1) ?></span>
                    <small class="text-muted">(<?= $ratingData['review_count'] ?>)</small>
                <?php else: ?>
                    <small class="text-muted">No ratings yet</small>
                <?php endif; ?>
            </div>

            <hr>

            <h5>Description</h5>
            <p><?= nl2br(htmlspecialchars($parking['description'])) ?></p>

            <hr>


            <!-- Reviews list -->
            <div class="mt-3">
                <h5>Reviews</h5>
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">No reviews yet. Be the first to review this parking.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <div><strong><?= htmlspecialchars($r['username']) ?></strong></div>
                                <div class="text-warning">★ <?= (int)$r['rating'] ?></div>
                            </div>
                            <?php if (!empty($r['comment'])): ?>
                                <div class="mt-1"><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
                            <?php endif; ?>
                            <small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Review form (only for logged-in users) -->
            <div class="mt-3">
                <h6>Add / Update your review</h6>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <p class="text-muted">Please <a href="login.php">login</a> to leave a review.</p>
                <?php else: ?>
                    <form method="POST" action="review_submit.php">
                        <input type="hidden" name="parking_id" value="<?= $id ?>">

                        <div class="mb-2">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" required>
                                <?php for ($s=1;$s<=5;$s++): ?>
                                    <option value="<?= $s ?>" <?= ($userReview && (int)$userReview['rating'] === $s) ? 'selected' : '' ?>><?= $s ?> ★</option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Comment (optional)</label>
                            <textarea name="comment" class="form-control" rows="3" maxlength="500"><?= $userReview ? htmlspecialchars($userReview['comment']) : '' ?></textarea>
                        </div>

                        <button class="btn btn-outline-primary">Submit review</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
