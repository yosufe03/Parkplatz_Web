<?php
session_start();
include("includes/db_connect.php");

$today = date('Y-m-d');

$district_id = isset($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
$neighborhood_id = isset($_GET['neighborhood_id']) ? (int)$_GET['neighborhood_id'] : 0;
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

// active filter flags (define early so we can use them to load names)
$useDistrict = ($district_id > 0);
$useNeighborhood = ($neighborhood_id > 0);

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

// Load selected district/neighborhood names for display
$selectedDistrictName = null;
$selectedNeighborhoodName = null;
if ($useDistrict) {
    $dstmt = $conn->prepare("SELECT name FROM districts WHERE id = ? LIMIT 1");
    $dstmt->bind_param('i', $district_id);
    $dstmt->execute();
    $dres = $dstmt->get_result();
    if ($dr = $dres->fetch_assoc()) $selectedDistrictName = $dr['name'];
    $dstmt->close();
}
if ($useNeighborhood) {
    $nstmt = $conn->prepare("SELECT name FROM neighborhoods WHERE id = ? LIMIT 1");
    $nstmt->bind_param('i', $neighborhood_id);
    $nstmt->execute();
    $nres = $nstmt->get_result();
    if ($nr = $nres->fetch_assoc()) $selectedNeighborhoodName = $nr['name'];
    $nstmt->close();
}

$errors = [];
$parkings = [];

/* ---------- Decide active filters ---------- */
$useDistrict = ($district_id > 0);
$useNeighborhood = ($neighborhood_id > 0);
$useDates = (isValidDate($from) && isValidDate($to) && $from !== '' && $to !== '');

/* ---------- Validate dates if used ---------- */
if ($useDates) {
    if ($from < $today || $to < $today) {
        $errors[] = "Vergangene Daten sind nicht erlaubt.";
    }
    if ($to < $from) {
        $errors[] = "Enddatum muss nach dem Startdatum liegen.";
    }
} else {
    // one date filled but not the other → error
    if (($from !== '' && $to === '') || ($from === '' && $to !== '')) {
        $errors[] = "Bitte sowohl Von als auch Bis auswählen (oder beide leer lassen).";
    } elseif (
            ($from !== '' && !isValidDate($from)) ||
            ($to !== '' && !isValidDate($to))
    ) {
        $errors[] = "Bitte gültige Daten auswählen.";
    }
}

/* ---------- Build query ---------- */
if (empty($errors)) {

    $types = "";
    $params = [];

    if ($useDates) {
        // strict availability + no overlapping booking
        $sql = "
            SELECT DISTINCT p.*
            FROM parkings p
            JOIN parking_availability a ON a.parking_id = p.id
            WHERE p.status = 'approved'
              AND a.available_from <= ?
              AND a.available_to   >= ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.parking_id = p.id
                    AND b.booking_start <= ?
                    AND b.booking_end   >= ?
              )
        ";
        $types .= "ssss";
        $params[] = $from;
        $params[] = $to;
        $params[] = $to;
        $params[] = $from;
    } else {
        // no date filter → show all approved parkings
        $sql = "
            SELECT p.*
            FROM parkings p
            WHERE p.status = 'approved'
        ";
    }

    if ($useDistrict) {
        $sql .= " AND p.district_id = ? ";
        $types .= "i";
        $params[] = $district_id;
    }
    if ($useNeighborhood) {
        $sql .= " AND p.neighborhood_id = ? ";
        $types .= "i";
        $params[] = $neighborhood_id;
    }

    $sql .= " ORDER BY p.id DESC ";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $parkings[] = $row;
    }
    $stmt->close();

    // Fetch average ratings for results (single query to avoid N+1)
    if (!empty($parkings)) {
        $ids = array_map(function($p){ return (int)$p['id']; }, $parkings);
        $in = implode(',', $ids);
        $ratings = [];
        $revSql = "SELECT parking_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM parking_reviews WHERE parking_id IN ($in) GROUP BY parking_id";
        if ($revStmt = $conn->prepare($revSql)) {
            $revStmt->execute();
            $revRes = $revStmt->get_result();
            while ($r = $revRes->fetch_assoc()) {
                $ratings[(int)$r['parking_id']] = $r;
            }
            $revStmt->close();
        }
        // Favorites for current user (single query)
        $favorites = [];
        if (isset($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
            $favSql = "SELECT parking_id FROM favorites WHERE user_id = ? AND parking_id IN ($in)";
            if ($favStmt = $conn->prepare($favSql)) {
                $favStmt->bind_param('i', $uid);
                $favStmt->execute();
                $favRes = $favStmt->get_result();
                while ($f = $favRes->fetch_assoc()) {
                    $favorites[(int)$f['parking_id']] = true;
                }
                $favStmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<?php
$pageTitle = "Suche";
include("includes/header.php");
?>
<body>

<div class="container mt-5">

    <h2 class="mb-3">Suchergebnisse</h2>

    <!-- ✅ REUSABLE SEARCH FORM (values preserved automatically) -->
    <?php include("includes/search_form.php"); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>

        <div class="alert alert-info mt-3">
            <strong>Distrikt:</strong> <?= $useDistrict ? htmlspecialchars($selectedDistrictName ?? (string)$district_id) : 'Alle' ?> |
            <strong>Stadtteil:</strong> <?= $useNeighborhood ? htmlspecialchars($selectedNeighborhoodName ?? (string)$neighborhood_id) : 'Alle' ?> |
            <strong>Zeitraum:</strong> <?= $useDates ? htmlspecialchars("$from → $to") : 'Alle' ?> |
            <strong>Treffer:</strong> <?= count($parkings) ?>
        </div>

        <?php if (empty($parkings)): ?>
            <div class="alert alert-warning">
                Keine Parkplätze gefunden.
            </div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($parkings as $row): ?>
                <?php
                $parkingId = (int)$row['id'];

                // image selection logic unchanged
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

                        <a href="parking.php?id=<?= $parkingId ?>
                           <?php if ($useDates): ?>&start_date=<?= urlencode($from) ?>&end_date=<?= urlencode($to)?>
                           <?php endif; ?>"
                           class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm">

                            <?php if ($mainImage): ?>
                                <img src="<?= htmlspecialchars($mainImage) ?>"
                                     class="card-img-top"
                                     style="height:180px;object-fit:cover;">
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
                                <?php
                                    $districtName = '';
                                    $neighborhoodName = '';
                                    if (!empty($row['district_id'])) {
                                        $dq = $conn->prepare("SELECT name FROM districts WHERE id = ? LIMIT 1");
                                        $dqid = (int)$row['district_id'];
                                        $dq->bind_param('i', $dqid);
                                        $dq->execute();
                                        $dres = $dq->get_result();
                                        if ($dr = $dres->fetch_assoc()) $districtName = $dr['name'];
                                        $dq->close();
                                    }
                                    if (!empty($row['neighborhood_id'])) {
                                        $nq = $conn->prepare("SELECT name FROM neighborhoods WHERE id = ? LIMIT 1");
                                        $nqid = (int)$row['neighborhood_id'];
                                        $nq->bind_param('i', $nqid);
                                        $nq->execute();
                                        $nres = $nq->get_result();
                                        if ($nr = $nres->fetch_assoc()) $neighborhoodName = $nr['name'];
                                        $nq->close();
                                    }
                                ?>
                                <p><strong>Distrikt:</strong> <?= htmlspecialchars($districtName ?: '—') ?> <br>
                                <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName ?: '—') ?></p>
                                <p><strong>Preis:</strong> €<?= number_format((float)$row['price'], 2) ?> / Tag</p>
                                <?php
                                    $r = $ratings[$parkingId] ?? null;
                                    $isFav = isset($favorites[$parkingId]);
                                ?>
                                <p>
                                    <strong>Rating:</strong>
                                    <?php if ($r): ?>
                                        <span class="text-warning">★ <?= number_format((float)$r['avg_rating'], 1) ?></span>
                                        <small class="text-muted">(<?= (int)$r['review_count'] ?>)</small>
                                    <?php else: ?>
                                        <small class="text-muted">No ratings yet</small>
                                    <?php endif; ?>
                                </p>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form method="POST" action="favorite_toggle.php" class="mt-2">
                                        <input type="hidden" name="parking_id" value="<?= $parkingId ?>">
                                        <?php if ($isFav): ?>
                                            <input type="hidden" name="action" value="remove">
                                            <button class="btn btn-sm btn-outline-danger">♥</button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="add">
                                            <button class="btn btn-sm btn-outline-primary">♡</button>
                                        <?php endif; ?>
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
