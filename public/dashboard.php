<?php
session_start();
include("includes/db_connect.php");

// Prüfen, ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Prüfen, ob Admin  / session var erstellen
$stmtUser = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$currentUser = $resultUser->fetch_assoc();
$isAdmin = $currentUser['role'] === 'admin';
?>

<!DOCTYPE html>
<html lang="de">
<?php
$pageTitle = "Dashboard";
include("includes/header.php");
?>

<body>

<div class="container mt-5 text-center">
    <h1>Willkommen, <?= htmlspecialchars($username) ?>!</h1>
    <p class="lead mt-3">Hier kannst du deine Parkplätze verwalten und neue hinzufügen.</p>

    <!-- Dashboard Buttons -->
    <div class="row justify-content-center mt-4">
        <div class="col-md-6">
            <a href="parking_add.php" class="btn btn-success w-100 mb-3">Neuen Parkplatz hinzufügen</a>
            <a href="my_parkings.php" class="btn btn-secondary w-100 mb-3">Meine Parkplätze verwalten</a>
            <a href="my_bookings.php" class="btn btn-info w-100 mb-3">Meine Buchungen</a>

            <?php if ($isAdmin): ?>
                <a href="pending_parkings.php" class="btn btn-warning w-100 mb-3">Parkplätze freigeben</a>
                <a href="all_parkings.php" class="btn btn-primary w-100 mb-3">Alle Parkplätze anzeigen</a>
                <a href="user_manage.php" class="btn btn-danger w-100 mb-3">User moderieren</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- User's recent parking offers -->
    <h4 class="mt-5">Letzte Parkplätze</h4>
    <div class="list-group mt-3">
        <?php
        if ($isAdmin) {
            $stmt = $conn->prepare("
                SELECT p.*, u.username AS owner_name 
                FROM parkings p 
                LEFT JOIN users u ON p.owner_id = u.id 
                ORDER BY p.id DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $stmt = $conn->prepare("
                SELECT * FROM parkings 
                WHERE owner_id=? 
                ORDER BY id DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        while ($row = $result->fetch_assoc()):
            ?>
            <a href="parking_edit.php?id=<?= $row['id'] ?>" class="list-group-item list-group-item-action">
                <?= htmlspecialchars($row['title']) ?> — <?= htmlspecialchars($row['location']) ?>

                <?php if ($isAdmin): ?>
                    <small class="text-muted"> (Besitzer: <?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?>)</small>
                <?php endif; ?>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- Earnings & Booking Statistics for owners -->
        <h4 class="mt-5">Einnahmen & Statistiken</h4>

        <?php
        // Total earnings and total bookings for this owner's parkings
        $totStmt = $conn->prepare(
            "SELECT 
                COUNT(b.id) AS total_bookings,
                COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS total_earnings
             FROM bookings b
             JOIN parkings p ON p.id = b.parking_id
             WHERE p.owner_id = ?"
        );
        $totStmt->bind_param("i", $userId);
        $totStmt->execute();
        $totRes = $totStmt->get_result();
        $totRow = $totRes->fetch_assoc();
        $totStmt->close();

        // Upcoming bookings
        $upStmt = $conn->prepare(
            "SELECT COUNT(b.id) AS upcoming FROM bookings b JOIN parkings p ON p.id = b.parking_id WHERE p.owner_id = ? AND b.booking_end >= CURDATE()"
        );
        $upStmt->bind_param("i", $userId);
        $upStmt->execute();
        $upRes = $upStmt->get_result();
        $upRow = $upRes->fetch_assoc();
        $upStmt->close();

        // Earnings last 30 days (by booking creation)
        $l30Stmt = $conn->prepare(
            "SELECT COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS last30 FROM bookings b JOIN parkings p ON p.id = b.parking_id WHERE p.owner_id = ? AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $l30Stmt->bind_param("i", $userId);
        $l30Stmt->execute();
        $l30Res = $l30Stmt->get_result();
        $l30Row = $l30Res->fetch_assoc();
        $l30Stmt->close();

        // Per-parking breakdown
        $perStmt = $conn->prepare(
            "SELECT p.id, p.title, COUNT(b.id) AS bookings, COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day),0) AS earnings
             FROM parkings p
             LEFT JOIN bookings b ON b.parking_id = p.id
             WHERE p.owner_id = ?
             GROUP BY p.id
             ORDER BY earnings DESC"
        );
        $perStmt->bind_param("i", $userId);
        $perStmt->execute();
        $perRes = $perStmt->get_result();
        $perRows = [];
        while ($r = $perRes->fetch_assoc()) $perRows[] = $r;
        $perStmt->close();
        ?>

        <div class="row mt-3">
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted small">Gesamt-Buchungen</div>
                    <div class="fs-4 fw-bold"><?= (int)$totRow['total_bookings'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted small">Gesamt-Einnahmen</div>
                    <div class="fs-4 fw-bold">€<?= number_format((float)$totRow['total_earnings'], 2) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted small">Kommende Buchungen</div>
                    <div class="fs-4 fw-bold"><?= (int)$upRow['upcoming'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted small">Einnahmen (letzte 30 Tage)</div>
                    <div class="fs-4 fw-bold">€<?= number_format((float)$l30Row['last30'], 2) ?></div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h5>Pro Parkplatz</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Titel</th>
                            <th>Buchungen</th>
                            <th>Einnahmen</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perRows as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['title']) ?></td>
                                <td><?= (int)$p['bookings'] ?></td>
                                <td>€<?= number_format((float)$p['earnings'], 2) ?></td>
                                <td><a href="parking_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">Bearbeiten</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

</body>
</html>
