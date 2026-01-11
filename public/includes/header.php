<?php
// Load configuration FIRST (if not already loaded)
if (!isset($config) || !is_array($config)) {
    $config = include __DIR__ . '/../config.php';
}

// Include utilities BEFORE parking_utils to prevent double inclusion
include_once __DIR__ . '/db_connect.php';
include_once __DIR__ . '/security.php';
include_once __DIR__ . '/validation.php';

include_once __DIR__ . '/parking_utils.php';

// Configure session settings BEFORE starting session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if ($config['app_env'] === 'production') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.gc_maxlifetime', (string)$config['session_timeout']);

    // NOW start the session
    session_start();
}


// Helper function to clear remember_me cookie and session
function logout_user($reason = '') {
    session_destroy();
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
    if ($reason) {
        header("Location: login.php?$reason=1");
    } else {
        header("Location: login.php");
    }
    exit;
}

// Auto-login from remember_me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    if (strlen($token) > 64) {
        $email = substr($token, 0, -64);
        $expected_sig = hash_hmac('sha256', $email, $config['app_secret_key']);
        $actual_sig = substr($token, -64);

        if (hash_equals($actual_sig, $expected_sig)) {
            $stmt = $conn->prepare("SELECT id, username, role, active FROM users WHERE email = ? AND active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
            }
        }
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Check session timeout and user activity
if ($isLoggedIn) {
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $config['session_timeout']) {
        logout_user('expired');
    }

    $_SESSION['last_activity'] = time();
    $_SESSION['is_admin'] = is_admin($_SESSION['user_id']);

    // Verify user is still active
    $stmt = $conn->prepare("SELECT active FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !$user['active']) {
        logout_user('locked');
    }

    // Check remember_me cookie integrity
    if (isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        if (strlen($token) > 64) {
            $email = substr($token, 0, -64);
            $expected_sig = hash_hmac('sha256', $email, $config['app_secret_key']);
            $actual_sig = substr($token, -64);

            if (!hash_equals($actual_sig, $expected_sig)) {
                logout_user('tampered');
            }
        }
    }
} else {
    $_SESSION['is_admin'] = false;
}

// Validate CSRF token on POST/PUT/DELETE requests
if (!validate_csrf_on_post()) {
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

// Generate CSRF token for forms
generate_csrf_token();
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? htmlspecialchars("$pageTitle | ParkShare") : "ParkShare" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link" href="my_favorites.php">Favorites</a></li>
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

