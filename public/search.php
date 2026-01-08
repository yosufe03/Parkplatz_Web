<?php
session_start();
include("includes/db_connect.php");

$today = date('Y-m-d');

$location = trim($_GET['location'] ?? '');
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

$errors = [];
$parkings = [];

/* ---------- Decide active filters ---------- */
$useLocation = ($location !== '');
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

    if ($useLocation) {
        $sql .= " AND p.location LIKE ? ";
        $types .= "s";
        $params[] = "%" . $location . "%";
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
            <strong>Ort:</strong> <?= htmlspecialchars($useLocation ? $location : 'Alle') ?> |
            <strong>Zeitraum:</strong>
            <?= $useDates ? htmlspecialchars("$from → $to") : 'Alle' ?> |
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
                                <p><strong>Ort:</strong> <?= htmlspecialchars($row['location']) ?></p>
                                <p><strong>Preis:</strong> €<?= number_format((float)$row['price'], 2) ?> / Tag</p>
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
