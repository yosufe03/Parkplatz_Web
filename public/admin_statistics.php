<?php
include_once "includes/parking_utils.php";

$pageTitle = "Admin Statistiken";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Admin status is stored in session by header.php
if (!($_SESSION['is_admin'] ?? false)) {
    header("Location: index.php");
    exit;
}

// Get statistics
$stats = get_admin_stats();
$topUsers = get_top_users(10);
$pendingParkings = get_pending_parkings(10);
?>

<!DOCTYPE html>
<html lang="de">
<body>
<div class="container mt-5">
    <h2>📊 Plattform Übersicht</h2>

    <div class="row mt-4 mb-5">
        <div class="col-md-2">
            <div class="card p-3 bg-primary text-white h-100">
                <div class="small">Nutzer</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total_users'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-success text-white h-100">
                <div class="small">Parkplätze</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total_parkings'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-info text-white h-100">
                <div class="small">Buchungen</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total_bookings'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-warning text-dark h-100">
                <div class="small">Einnahmen</div>
                <div class="fs-4 fw-bold">€<?= number_format((float)$stats['total_revenue'], 0) ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-danger text-white h-100">
                <div class="small">Aktiv</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['active_bookings'] ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 bg-secondary text-white h-100">
                <div class="small">Ausstehend</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['pending_count'] ?></div>
            </div>
        </div>
    </div>

    <h5 class="mt-5">Top Vermieter</h5>
    <table class="table table-sm">
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

