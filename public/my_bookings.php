<?php
include_once "includes/parking_utils.php";

// Include header FIRST to start session
$pageTitle = "Meine Buchungen";
include "includes/header.php";

// NOW check auth - session is started
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Optional: handle cancel action (simple version)
if (isset($_POST['cancel_booking_id'])) {
    $cancel_id = (int)$_POST['cancel_booking_id'];

    // Only delete user's own booking
    $del = $conn->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $cancel_id, $user_id);
    $del->execute();
    $del->close();

    header("Location: my_bookings.php?canceled=1");
    exit;
}

// Fetch bookings + parking info
$stmt = $conn->prepare("
    SELECT 
        b.id AS booking_id,
        b.booking_start,
        b.booking_end,
        b.price_day,
        b.created_at,
        p.id AS parking_id,
        p.title,
        p.district_id,
        p.neighborhood_id
    FROM bookings b
    JOIN parkings p ON p.id = b.parking_id
    WHERE b.user_id = ?
    ORDER BY b.booking_start DESC, b.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$bookings = [];
while ($row = $res->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

function daysInclusive($start, $end) {
    $s = new DateTime($start);
    $e = new DateTime($end);
    return $s->diff($e)->days + 1;
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="de">
<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Meine Buchungen</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">← Dashboard</a>
    </div>

    <?php if (isset($_GET['canceled']) && $_GET['canceled'] == '1'): ?>
        <div class="alert alert-success">Buchung wurde storniert.</div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="mb-0 text-muted">Du hast noch keine Buchungen.</p>
            </div>
        </div>
    <?php else: ?>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="p-2 bg-white border rounded">
                            <div class="text-muted small">Gesamt</div>
                            <div class="fs-5 fw-semibold"><?= count($bookings) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php
                        $upcoming = 0;
                        foreach ($bookings as $b) {
                            if ($b['booking_end'] >= $today) $upcoming++;
                        }
                        ?>
                        <div class="p-2 bg-white border rounded">
                            <div class="text-muted small">Aktiv / Kommend</div>
                            <div class="fs-5 fw-semibold"><?= $upcoming ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php $past = count($bookings) - $upcoming; ?>
                        <div class="p-2 bg-white border rounded">
                            <div class="text-muted small">Vergangen</div>
                            <div class="fs-5 fw-semibold"><?= $past ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($bookings as $b): ?>
            <?php
            $nDays = daysInclusive($b['booking_start'], $b['booking_end']);
            $priceDay = (float)$b['price_day'];
            $total = $nDays * $priceDay;

            $status = "Vergangen";
            $badge = "secondary";
            if ($b['booking_start'] <= $today && $b['booking_end'] >= $today) {
                $status = "Aktiv";
                $badge = "success";
            } elseif ($b['booking_start'] > $today) {
                $status = "Kommend";
                $badge = "primary";
            }

            $canCancel = ($b['booking_start'] > $today); // cancel only if not started
            ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($b['title']) ?></h5>
                            <div class="text-muted mb-2">
                                <?php
                                    $districtName = get_district_name((int)$b['district_id']);
                                    $neighborhoodName = get_neighborhood_name((int)$b['neighborhood_id']);
                                    $location = trim("$districtName, $neighborhoodName", ', ');
                                    echo htmlspecialchars($location ?: '—');
                                ?>
                            </div>

                            <div class="mb-1">
                                <span class="badge bg-<?= $badge ?>"><?= $status ?></span>
                                <span class="ms-2 text-muted small">Buchung #<?= (int)$b['booking_id'] ?></span>
                            </div>

                            <div class="mt-2">
                                <div><strong>Zeitraum:</strong> <?= htmlspecialchars($b['booking_start']) ?> → <?= htmlspecialchars($b['booking_end']) ?></div>
                                <div><strong>Tage:</strong> <?= (int)$nDays ?></div>
                                <div><strong>Preis/Tag (fix):</strong> €<?= number_format($priceDay, 2) ?></div>
                                <div><strong>Gesamt:</strong> €<?= number_format($total, 2) ?></div>
                            </div>
                        </div>

                        <div class="text-end">
                            <a class="btn btn-sm btn-outline-primary mb-2"
                               href="parking.php?id=<?= (int)$b['parking_id'] ?>">
                                Parkplatz ansehen
                            </a>

                            <?php if ($canCancel): ?>
                                <form method="POST" onsubmit="return confirm('Buchung wirklich stornieren?');">
                                    <input type="hidden" name="cancel_booking_id" value="<?= (int)$b['booking_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Stornieren
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                    Stornieren nicht möglich
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($b['created_at'])): ?>
                        <hr>
                        <small class="text-muted">Erstellt am: <?= htmlspecialchars($b['created_at']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
