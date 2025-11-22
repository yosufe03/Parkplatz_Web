<?php
session_start();
include("includes/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if admin
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
            <?php endif; ?>
        </div>
    </div>

    <!-- User's recent parking offers -->
    <h4 class="mt-5">Letzte Parkplätze</h4>
    <div class="list-group mt-3">
        <?php
        if ($isAdmin) {
            $stmt = $conn->prepare("SELECT p.*, u.username AS owner_name FROM parkings p LEFT JOIN users u ON p.owner_id = u.id ORDER BY p.id DESC");
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $stmt = $conn->prepare("SELECT * FROM parkings WHERE owner_id=? ORDER BY id DESC");
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
</div>

</body>
</html>
