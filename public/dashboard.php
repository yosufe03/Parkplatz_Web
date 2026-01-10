<?php
// Include header FIRST to start session
$pageTitle = "Dashboard";
include("includes/header.php");

// NOW check if logged in - session is started
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
            <a href="statistics.php" class="btn btn-primary w-100 mb-3">Statistiken</a>

            <?php if ($isAdmin): ?>
                <a href="pending_parkings.php" class="btn btn-warning w-100 mb-3">Parkplätze freigeben</a>
                <a href="all_parkings.php" class="btn btn-primary w-100 mb-3">Alle Parkplätze anzeigen</a>
                <a href="admin_statistics.php" class="btn btn-info w-100 mb-3">Plattform Statistiken</a>
                <a href="areas_manage.php" class="btn btn-outline-primary w-100 mb-3">Gebiete verwalten</a>
                <a href="user_manage.php" class="btn btn-danger w-100 mb-3">User moderieren</a>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
