<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? htmlspecialchars("$pageTitle | ParkShare") : "ParkShare" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark position-relative">
    <div class="container-fluid d-flex justify-content-between align-items-center position-relative">

        <!-- Left: Brand -->
        <a class="navbar-brand" href="index.php">ParkShare</a>

        <!-- Hamburger button (mobile only, centered) -->
        <button class="navbar-toggler d-lg-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarMain"
                aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Center: menu items -->
        <div class="collapse navbar-collapse justify-content-center" id="navbarMain">
            <ul class="navbar-nav text-center">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="parking_add.php">Add Parking</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_parkings.php">My Parkings</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_bookings.php">My Bookings</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Right: login/username always visible -->
        <div class="d-flex align-items-center ms-auto">
            <?php if ($isLoggedIn): ?>
                <span class="navbar-text text-white me-2"> <?= htmlspecialchars($username) ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
                <a href="register.php" class="btn btn-outline-light btn-sm">Register</a>
            <?php endif; ?>
        </div>

    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
    /* Mobile-first: Hamburger button centered and fully visible */
    .navbar-toggler.d-lg-none {
        position: absolute;
        top: 0;           /* attach to top of navbar */
        left: 50%;
        transform: translateX(-50%);
        z-index: 2;
        display: flex;
        align-items: center;
    }

    /* Collapse menu opens below hamburger on mobile */
    @media (max-width: 991.98px) {
        #navbarMain {
            flex-direction: column;
            width: 100%;
            text-align: center;
            /*margin-top: 3rem; !* push menu below hamburger *!*/
        }

        .navbar-nav .nav-item {
            width: 100%; /* full width for easier tapping */
        }
    }

    /* Desktop: center menu items */
    @media (min-width: 992px) {
        #navbarMain {
            justify-content: center !important;
        }
    }
</style>
