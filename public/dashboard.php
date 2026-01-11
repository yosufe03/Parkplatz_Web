<?php
$pageTitle = "Dashboard";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<body>

<div class="container mt-5 text-center">
    <h1>Willkommen, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>

    <div class="row justify-content-center mt-4">
        <div class="col-md-6">
            <a href="parking_add.php" class="btn btn-success w-100 mb-3">Neuen Parkplatz hinzufügen</a>
            <a href="my_parkings.php" class="btn btn-secondary w-100 mb-3">Meine Parkplätze verwalten</a>
            <a href="my_bookings.php" class="btn btn-info w-100 mb-3">Meine Buchungen</a>
            <a href="statistics.php" class="btn btn-primary w-100 mb-3">Statistiken</a>

            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
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