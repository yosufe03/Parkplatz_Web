<?php
include_once "includes/parking_utils.php";

$pageTitle = "Parkplatz Details";
include "includes/header.php";

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Handle favorite toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Sie müssen angemeldet sein.';
    } else {
        toggle_favorite($id, (int)$_SESSION['user_id'], $_POST['action'] ?? 'add');
    }
}

// Handle review deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Sie müssen angemeldet sein.';
    } else {
        $reviewId = (int)$_POST['review_id'];
        $error = delete_review($reviewId, $_SESSION['user_id'], $_SESSION['is_admin']);
        if ($error) {
            $_SESSION['error'] = $error;
        } else {
            $_SESSION['success'] = 'Bewertung erfolgreich gelöscht.';
        }
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Sie müssen angemeldet sein.';
    } else {
        $parkingId = (int)($_POST['parking_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = $_POST['comment'] ?? null;
        $error = submit_review($parkingId, (int)$_SESSION['user_id'], $rating, $comment);
        if ($error) {
            $_SESSION['error'] = $error;
        } else {
            $_SESSION['success'] = 'Bewertung gespeichert.';
        }
    }
}

$parking = get_parking_by_id($id);
if (!$parking) {
    header("Location: index.php");
    exit;
}

$isOwner = isset($_SESSION['user_id']) && $parking['owner_id'] == $_SESSION['user_id'];
$isAdmin = $_SESSION['is_admin'] ?? false;
if ($parking['status'] === 'rejected' && !$isOwner && !$isAdmin) {
    header("Location: index.php");
    exit;
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
                    <div class="carousel-indicators">
                        <?php foreach ($images as $i => $img): ?>
                            <button type="button" data-bs-target="#carousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="carousel-inner">
                        <?php foreach ($images as $i => $img): ?>
                            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($img) ?>" class="parking-img" alt="Parkplatz">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Vorheriges Bild</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Nächstes Bild</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php include "includes/calendar.php"; ?>
        </div>

        <!-- RIGHT -->
        <div class="col-md-5">
            <h2><?= htmlspecialchars($parking['title']) ?></h2>

            <p class="text-muted">
                <strong>Distrikt:</strong> <?= htmlspecialchars($districtName ?: '—') ?> <br>
                <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName ?: '—') ?>
            </p>

            <h4 class="text-primary">€<?= number_format((float)$parking['price'], 2) ?> / Tag</h4>

            <?php if (!empty($parking['owner_name'])): ?>
                <p><strong>Besitzer:</strong> <?= htmlspecialchars($parking['owner_name']) ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" class="mb-2">
                    <input type="hidden" name="toggle_favorite" value="1">
                    <input type="hidden" name="parking_id" value="<?= $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <?php if ($isFavorite): ?>
                        <input type="hidden" name="action" value="remove">
                        <button class="btn btn-sm btn-outline-danger">♥ Entfernen</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                        <button class="btn btn-sm btn-outline-primary">♡ Favorisieren</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <div class="mb-2">
                <strong>Bewertung:</strong>
                <?php if ($ratingData['review_count'] > 0): ?>
                    <span class="text-warning">★ <?= number_format($ratingData['avg_rating'], 1) ?></span>
                    <small class="text-muted">(<?= $ratingData['review_count'] ?>)</small>
                <?php else: ?>
                    <small class="text-muted">Keine Bewertungen</small>
                <?php endif; ?>
            </div>

            <hr>

            <h5>Beschreibung</h5>
            <p><?= nl2br(htmlspecialchars($parking['description'])) ?></p>

            <hr>

            <div class="mt-3">
                <h5>Bewertungen</h5>
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">Noch keine Bewertungen. Seien Sie der erste!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars($r['username']) ?></strong>
                                    <span class="text-warning">★ <?= (int)$r['rating'] ?></span>
                                </div>
                                <?php if (isset($_SESSION['user_id']) && ($r['user_id'] == $_SESSION['user_id'] || $_SESSION['is_admin'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="delete_review" value="1">
                                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bewertung wirklich löschen?')">Löschen</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($r['comment'])): ?>
                                <p class="mt-1"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                            <?php endif; ?>
                            <small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-3">
                <h6>Bewertung abgeben</h6>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <p class="text-muted">Bitte <a href="login.php">melden Sie sich an</a>, um eine Bewertung abzugeben.</p>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="submit_review" value="1">
                        <input type="hidden" name="parking_id" value="<?= $id ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                        <div class="mb-2">
                            <label class="form-label">Bewertung</label>
                            <select name="rating" class="form-select" required>
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <option value="<?= $s ?>" <?= ($userReview && (int)$userReview['rating'] === $s) ? 'selected' : '' ?>>
                                        <?= $s ?> ★
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Kommentar (optional)</label>
                            <textarea name="comment" class="form-control" rows="3" maxlength="500"><?= $userReview && !empty($userReview['comment']) ? htmlspecialchars($userReview['comment']) : '' ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Bewertung abgeben</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
