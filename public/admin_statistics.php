<?php
include_once "includes/parking_utils.php";

// Include header FIRST to start session
$pageTitle = "Admin Statistiken";
include "includes/header.php";

// NOW check auth - session is started
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Check if admin
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param('i', $userId);
$roleStmt->execute();
$roleResult = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$roleResult || $roleResult['role'] !== 'admin') {
    die("Nur Administratoren können diese Seite anschauen.");
}

// Total stats
$statsStmt = $conn->prepare(
    "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM parkings) as total_parkings,
        (SELECT COUNT(*) FROM bookings) as total_bookings,
        (SELECT COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) FROM bookings b) AS total_revenue,
        (SELECT COUNT(*) FROM bookings WHERE booking_start <= CURDATE() AND booking_end >= CURDATE()) as active_bookings,
        (SELECT COUNT(*) FROM parkings WHERE status = 'pending') as pending_count"
);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

// Top users
$topUsersStmt = $conn->prepare(
    "SELECT u.username, COUNT(p.id) as parking_count, COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS total_earnings
     FROM users u LEFT JOIN parkings p ON p.owner_id = u.id LEFT JOIN bookings b ON b.parking_id = p.id
     GROUP BY u.id HAVING parking_count > 0 ORDER BY total_earnings DESC LIMIT 10"
);
$topUsersStmt->execute();
$topUsers = $topUsersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$topUsersStmt->close();

// Pending parkings
$pendingStmt = $conn->prepare(
    "SELECT p.id, p.title, p.price, u.username FROM parkings p LEFT JOIN users u ON p.owner_id = u.id WHERE p.status = 'pending' ORDER BY p.created_at ASC LIMIT 10"
);
$pendingStmt->execute();
$pendingParkings = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pendingStmt->close();
?>

<!DOCTYPE html>
<html lang="de">
<body>
<div class="container-fluid mt-5">
    <h2>📊 Plattform Übersicht</h2>

    <!-- Metrics -->
    <div class="row mt-4 mb-5">
        <div class="col-md-2">
            <div class="card p-3 bg-primary text-white">
                <div class="small">Nutzer</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total_users'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-success text-white">
                <div class="small">Parkplätze</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total_parkings'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-info text-white">
                <div class="small">Buchungen</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total_bookings'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-warning text-dark">
                <div class="small">Einnahmen</div>
                <div class="fs-4 fw-bold">€<?= number_format((float)$stats['total_revenue'], 0) ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-danger text-white">
                <div class="small">Aktiv</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['active_bookings'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-secondary text-white">
                <div class="small">Ausstehend</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['pending_count'] ?></div>
            </div>
        </div>
    </div>

    <!-- Top Users -->
    <h5 class="mt-5">Top Vermieter</h5>
    <table class="table table-sm mb-5">
        <thead>
            <tr>
                <th>Nutzer</th>
                <th>Parkplätze</th>
                <th>Einnahmen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topUsers as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= (int)$u['parking_count'] ?></td>
                    <td>€<?= number_format((float)$u['total_earnings'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pending Parkings -->
    <h5 class="mt-5">Ausstehend (<?= count($pendingParkings) ?>)</h5>
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Preis</th>
                <th>Besitzer</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingParkings as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['title']) ?></td>
                    <td>€<?= number_format((float)$p['price'], 0) ?></td>
                    <td><?= htmlspecialchars($p['username'] ?? '—') ?></td>
                    <td><a href="parking.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-primary">Anschauen</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>

