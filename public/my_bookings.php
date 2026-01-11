<?php
include_once "includes/parking_utils.php";

$pageTitle = "Meine Buchungen";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

if (isset($_POST['cancel_booking_id'])) {
    $bookingId = (int)$_POST['cancel_booking_id'];
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bookingId, $userId);
    $stmt->execute();
    $stmt->close();
    header("Location: my_bookings.php?canceled=1");
    exit;
}

$stmt = $conn->prepare("SELECT b.id, b.booking_start, b.booking_end, b.price_day, b.created_at, p.id AS parking_id, p.title, p.district_id, p.neighborhood_id FROM bookings b JOIN parkings p ON p.id = b.parking_id WHERE b.user_id = ? ORDER BY b.booking_start DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$upcoming = count(array_filter($bookings, fn($b) => $b['booking_end'] >= $today));
?>

<!DOCTYPE html>
<html lang="de">
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Meine Buchungen</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">← Dashboard</a>
    </div>

    <?php if (isset($_GET['canceled'])): ?>
        <div class="alert alert-success">Buchung wurde storniert.</div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="alert alert-info">Du hast noch keine Buchungen.</div>
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
                        <div class="p-2 bg-white border rounded">
                            <div class="text-muted small">Aktiv / Kommend</div>
                            <div class="fs-5 fw-semibold"><?= $upcoming ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 bg-white border rounded">
                            <div class="text-muted small">Vergangen</div>
                            <div class="fs-5 fw-semibold"><?= count($bookings) - $upcoming ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($bookings as $b):
            $days = get_days_between($b['booking_start'], $b['booking_end']);
            $total = $days * (float)$b['price_day'];
            $info = get_booking_status_info($b['booking_start'], $b['booking_end'], $today);
        ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($b['title']) ?></h5>
                            <div class="text-muted mb-2">
                                <?= htmlspecialchars(trim(get_district_name((int)$b['district_id']) . ', ' . get_neighborhood_name((int)$b['neighborhood_id']), ', ') ?: '—') ?>
                            </div>
                            <div class="mb-1">
                                <span class="badge bg-<?= $info['badge'] ?>"><?= $info['status'] ?></span>
                                <span class="ms-2 text-muted small">Buchung #<?= (int)$b['id'] ?></span>
                            </div>
                            <div class="mt-2">
                                <div><strong>Zeitraum:</strong> <?= htmlspecialchars($b['booking_start']) ?> → <?= htmlspecialchars($b['booking_end']) ?></div>
                                <div><strong>Tage:</strong> <?= $days ?></div>
                                <div><strong>Preis/Tag:</strong> €<?= number_format((float)$b['price_day'], 2) ?></div>
                                <div><strong>Gesamt:</strong> €<?= number_format($total, 2) ?></div>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="parking.php?id=<?= (int)$b['parking_id'] ?>" class="btn btn-sm btn-outline-primary mb-2">Parkplatz ansehen</a>
                            <?php if ($b['booking_start'] > $today): ?>
                                <form method="POST">
                                    <input type="hidden" name="cancel_booking_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Stornieren</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>Stornieren nicht möglich</button>
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
