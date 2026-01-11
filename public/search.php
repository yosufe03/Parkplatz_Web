<?php
include_once "includes/db_connect.php";
include_once "includes/parking_utils.php";

$pageTitle = "Suche";
include("includes/header.php");

$district_id = (int)($_GET['district_id'] ?? 0);
$neighborhood_id = (int)($_GET['neighborhood_id'] ?? 0);
$from = $_GET['from'] ?: date('Y-m-d');
$to = $_GET['to'] ?: date('Y-m-d', strtotime('+30 days'));

// Handle favorite toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Sie müssen angemeldet sein.';
    } else {
        $parkingId = (int)($_POST['parking_id'] ?? 0);
        if ($parkingId > 0) {
            toggle_favorite($parkingId, (int)$_SESSION['user_id'], $_POST['action'] ?? 'add');
        }
    }
}


$errors = [];
if ($district_id && ($err = is_valid_district($district_id))) $errors[] = $err;
if ($neighborhood_id && ($err = is_valid_neighborhood($neighborhood_id, $district_id))) $errors[] = $err;
if ($err = is_valid_date_range($from, $to)) $errors[] = $err;

$parkings = [];
$ratings = [];
$favorites = [];

if (empty($errors)) {
    $sql = 'SELECT * FROM parkings WHERE status = "approved"';
    $sql .= ' AND id NOT IN (SELECT parking_id FROM bookings WHERE booking_start <= ? AND booking_end >= ?)';
    if ($district_id > 0) $sql .= ' AND district_id = ' . $district_id;
    if ($neighborhood_id > 0) $sql .= ' AND neighborhood_id = ' . $neighborhood_id;
    $sql .= ' ORDER BY id DESC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $to, $from);
    $stmt->execute();
    $parkings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($parkings) {
        $ids = [];
        foreach ($parkings as $p) $ids[] = (int)$p['id'];
        $idList = implode(',', $ids);

        $ratingStmt = $conn->prepare("SELECT parking_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM parking_reviews WHERE parking_id IN ($idList) GROUP BY parking_id");
        $ratingStmt->execute();
        foreach ($ratingStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $ratings[(int)$r['parking_id']] = $r;
        }
        $ratingStmt->close();

        if (isset($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
            $favStmt = $conn->prepare("SELECT parking_id FROM favorites WHERE user_id = ? AND parking_id IN ($idList)");
            $favStmt->bind_param('i', $uid);
            $favStmt->execute();
            foreach ($favStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $f) {
                $favorites[(int)$f['parking_id']] = true;
            }
            $favStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<body>

<div class="container mt-5">
    <h2 class="mb-3">Suchergebnisse</h2>

    <?php include("includes/search_form.php"); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info mt-3">
            <?php
            $districtName = $district_id > 0 ? get_district_name($district_id) : 'Alle';
            $neighborhoodName = $neighborhood_id > 0 ? get_neighborhood_name($neighborhood_id) : 'Alle';
            ?>
            <strong>Distrikt:</strong> <?= htmlspecialchars($districtName) ?> |
            <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName) ?> |
            <strong>Zeitraum:</strong> <?= htmlspecialchars("$from → $to") ?> |
            <strong>Treffer:</strong> <?= count($parkings) ?>
        </div>

        <?php if (empty($parkings)): ?>
            <div class="alert alert-warning">Keine Parkplätze gefunden.</div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($parkings as $row): ?>
                <?php
                $id = (int)$row['id'];
                $img = !empty($row['main_image']) && file_exists("uploads/parkings/$id/" . $row['main_image'])
                    ? "uploads/parkings/$id/" . $row['main_image']
                    : (glob("uploads/parkings/$id/*.{jpg,jpeg,png}", GLOB_BRACE)[0] ?? null);
                ?>
                <div class="col-md-4 mb-3">
                    <a href="parking.php?id=<?= $id ?><?php if ($from && $to): ?>&start_date=<?= urlencode($from) ?>&end_date=<?= urlencode($to) ?><?php endif; ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="card-img-top parking-card-image" alt="<?= htmlspecialchars($row['title']) ?>">
                            <?php else: ?>
                                <div class="card-img-top parking-card-placeholder d-flex justify-content-center align-items-center bg-light text-muted">No Image</div>
                            <?php endif; ?>

                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 100, "...")) ?></p>
                                <p>
                                    <strong>Distrikt:</strong> <?= htmlspecialchars(get_district_name($row['district_id']) ?: '—') ?> <br>
                                    <strong>Stadtteil:</strong> <?= htmlspecialchars(get_neighborhood_name($row['neighborhood_id']) ?: '—') ?>
                                </p>
                                <p><strong>Preis:</strong> €<?= number_format((float)$row['price'], 2) ?> / Tag</p>
                                <p>
                                    <strong>Rating:</strong>
                                    <?php $r = $ratings[$id] ?? null; ?>
                                    <?php if ($r): ?>
                                        <span class="text-warning">★ <?= number_format((float)$r['avg_rating'], 1) ?></span>
                                        <small class="text-muted">(<?= (int)$r['review_count'] ?>)</small>
                                    <?php else: ?>
                                        <small class="text-muted">No ratings yet</small>
                                    <?php endif; ?>
                                </p>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="toggle_favorite" value="1">
                                        <input type="hidden" name="parking_id" value="<?= $id ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="<?= isset($favorites[$id]) ? 'remove' : 'add' ?>">
                                        <button class="btn btn-sm btn-outline-<?= isset($favorites[$id]) ? 'danger' : 'primary' ?>"><?= isset($favorites[$id]) ? '♥' : '♡' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>


