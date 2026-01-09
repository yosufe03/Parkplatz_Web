<?php
session_start();
include_once "includes/parking_utils.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Total earnings and bookings
$totStmt = $conn->prepare(
    "SELECT COUNT(b.id) AS total_bookings, COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS total_earnings
     FROM bookings b JOIN parkings p ON p.id = b.parking_id WHERE p.owner_id = ?"
);
$totStmt->bind_param("i", $userId);
$totStmt->execute();
$totRow = $totStmt->get_result()->fetch_assoc();
$totStmt->close();

// Upcoming bookings
$upStmt = $conn->prepare(
    "SELECT COUNT(b.id) AS upcoming FROM bookings b JOIN parkings p ON p.id = b.parking_id WHERE p.owner_id = ? AND b.booking_end >= CURDATE()"
);
$upStmt->bind_param("i", $userId);
$upStmt->execute();
$upRow = $upStmt->get_result()->fetch_assoc();
$upStmt->close();

// Earnings last 30 days
$l30Stmt = $conn->prepare(
    "SELECT COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS last30 FROM bookings b JOIN parkings p ON p.id = b.parking_id WHERE p.owner_id = ? AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$l30Stmt->bind_param("i", $userId);
$l30Stmt->execute();
$l30Row = $l30Stmt->get_result()->fetch_assoc();
$l30Stmt->close();

// Active parkings count
$activeParkingsStmt = $conn->prepare("SELECT COUNT(*) as active_count FROM parkings WHERE owner_id = ? AND status = 'approved'");
$activeParkingsStmt->bind_param("i", $userId);
$activeParkingsStmt->execute();
$activeParkingsRow = $activeParkingsStmt->get_result()->fetch_assoc();
$activeParkingsStmt->close();

$totalParkings = $activeParkingsRow['active_count'] ?? 1;
$avgEarnings = $totalParkings > 0 ? (float)$totRow['total_earnings'] / $totalParkings : 0;

// Per-parking breakdown
$perStmt = $conn->prepare(
    "SELECT p.id, p.title, COUNT(b.id) AS bookings, COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS earnings
     FROM parkings p LEFT JOIN bookings b ON b.parking_id = p.id WHERE p.owner_id = ? GROUP BY p.id ORDER BY earnings DESC"
);
$perStmt->bind_param("i", $userId);
$perStmt->execute();
$perRes = $perStmt->get_result();
$perRows = [];

while ($r = $perRes->fetch_assoc()) {
    $allBookingsStmt = $conn->prepare(
        "SELECT b.booking_start, b.booking_end, b.price_day, DATEDIFF(b.booking_end, b.booking_start) + 1 AS days, (DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day AS total_paid,
                CASE WHEN b.booking_end >= CURDATE() THEN 'upcoming' ELSE 'past' END AS booking_type
         FROM bookings b WHERE b.parking_id = ? ORDER BY b.booking_start DESC"
    );
    $allBookingsStmt->bind_param("i", $r['id']);
    $allBookingsStmt->execute();
    $allBookings = $allBookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $allBookingsStmt->close();

    $r['upcoming_bookings'] = array_filter($allBookings, fn($b) => $b['booking_type'] === 'upcoming');
    $r['past_bookings'] = array_filter($allBookings, fn($b) => $b['booking_type'] === 'past');
    $perRows[] = $r;
}
$perStmt->close();
?>

<!DOCTYPE html>
<html lang="de">
<?php $pageTitle = "Statistiken"; include "includes/header.php"; ?>
<body>
<div class="container-fluid mt-5">
    <h2>📊 Meine Statistiken</h2>

    <!-- Metrics -->
    <div class="row mt-4 mb-5">
        <div class="col-md-3">
            <div class="card p-3 bg-primary text-white">
                <div class="small">Gesamt-Buchungen</div>
                <div class="fs-4 fw-bold"><?= (int)$totRow['total_bookings'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 bg-success text-white">
                <div class="small">Gesamt-Einnahmen</div>
                <div class="fs-4 fw-bold">€<?= number_format((float)$totRow['total_earnings'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 bg-info text-white">
                <div class="small">Kommende Buchungen</div>
                <div class="fs-4 fw-bold"><?= (int)$upRow['upcoming'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 bg-warning text-dark">
                <div class="small">Letzte 30 Tage</div>
                <div class="fs-4 fw-bold">€<?= number_format((float)$l30Row['last30'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Parkings Table -->
    <h5 class="mt-5">Meine Parkplätze</h5>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th></th>
                    <th>Titel</th>
                    <th>Buchungen</th>
                    <th>Einnahmen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($perRows as $p): ?>
                    <tr style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#bookings<?= $p['id'] ?>">
                        <td><i class="bi bi-chevron-right"></i></td>
                        <td><a href="parking.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></td>
                        <td><span class="badge bg-info"><?= (int)$p['bookings'] ?></span></td>
                        <td><span class="badge bg-success">€<?= number_format((float)$p['earnings'], 2) ?></span></td>
                    </tr>
                    <tr class="collapse" id="bookings<?= $p['id'] ?>">
                        <td colspan="4" class="p-3">
                            <h6 class="text-primary mb-3">Kommende</h6>
                            <?php if (empty($p['upcoming_bookings'])): ?>
                                <p class="text-muted small">Keine kommenden Buchungen.</p>
                            <?php else: ?>
                                <table class="table table-sm table-borderless">
                                    <thead>
                                        <tr>
                                            <th>Von</th>
                                            <th>Bis</th>
                                            <th>Tage</th>
                                            <th>Preis/Tag</th>
                                            <th>Gesamt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($p['upcoming_bookings'] as $b): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($b['booking_start']) ?></td>
                                                <td><?= htmlspecialchars($b['booking_end']) ?></td>
                                                <td><span class="badge bg-primary"><?= (int)$b['days'] ?></span></td>
                                                <td>€<?= number_format((float)$b['price_day'], 2) ?></td>
                                                <td><strong class="text-success">€<?= number_format((float)$b['total_paid'], 2) ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <h6 class="text-secondary mt-4 mb-3">Verlauf</h6>
                            <?php if (empty($p['past_bookings'])): ?>
                                <p class="text-muted small">Keine vergangenen Buchungen.</p>
                            <?php else: ?>
                                <table class="table table-sm table-borderless">
                                    <thead>
                                        <tr>
                                            <th>Von</th>
                                            <th>Bis</th>
                                            <th>Tage</th>
                                            <th>Preis/Tag</th>
                                            <th>Gesamt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($p['past_bookings'] as $b): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($b['booking_start']) ?></td>
                                                <td><?= htmlspecialchars($b['booking_end']) ?></td>
                                                <td><span class="badge bg-secondary"><?= (int)$b['days'] ?></span></td>
                                                <td>€<?= number_format((float)$b['price_day'], 2) ?></td>
                                                <td><strong class="text-success">€<?= number_format((float)$b['total_paid'], 2) ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>

