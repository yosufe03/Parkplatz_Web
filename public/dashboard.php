<?php
include("includes/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - ParkShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("includes/header.php"); ?>

<div class="container mt-5 text-center">
    <h1>Willkommen, <?= htmlspecialchars($username) ?>!</h1>
    <p class="lead mt-3">Hier kannst du deine Parkplätze verwalten und neue hinzufügen.</p>

    <!-- Dashboard Buttons -->
    <div class="row justify-content-center mt-4">
        <div class="col-md-6">
            <a href="parking_add.php" class="btn btn-success w-100 mb-3">Neuen Parkplatz hinzufügen</a>
            <a href="my_parkings.php" class="btn btn-secondary w-100 mb-3">Meine Parkplätze verwalten</a>
            <a href="my_bookings.php" class="btn btn-info w-100 mb-3">Meine Buchungen</a>
        </div>
    </div>

    <!-- User's recent parking offers -->
    <h4 class="mt-5">Letzte Parkplätze</h4>
    <div class="list-group mt-3">
        <?php
        $stmt = $conn->prepare("SELECT * FROM parkings WHERE owner_id=? ORDER BY id DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()):
            ?>
            <a href="parking.php?id=<?= $row['id'] ?>" class="list-group-item list-group-item-action">
                <?= htmlspecialchars($row['title']) ?> — <?= htmlspecialchars($row['location']) ?>
            </a>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
