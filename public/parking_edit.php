<?php
session_start();
include_once "includes/parking_utils.php";

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$error = "";
$returnUrl = "my_parkings.php";

// Get parking ID from URL
$parkingId = (int)($_GET['id'] ?? 0) ?: null;
if (!$parkingId) die("Parkplatz-ID erforderlich.");
?>

<!DOCTYPE html>
<html lang="de">
<?php
$pageTitle = "Parkplatz bearbeiten";
include "includes/header.php";
?>

<body>
<div class="container mt-5">
    <h2>Parkplatz bearbeiten</h2>

    <?php include "includes/parking_form.php"; ?>
</div>
</body>
</html>
