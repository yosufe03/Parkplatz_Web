<?php
include_once "includes/parking_utils.php";

// Include header FIRST to start session
$pageTitle = "Neuer Parkplatz";
include "includes/header.php";

// NOW check auth - session is started
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$returnUrl = "dashboard.php";
?>


<body>
<div class="container mt-5">
    <h2>Neuen Parkplatz hinzufügen</h2>

    <?php include "includes/parking_form.php"; ?>
</div>
</body>
</html>
