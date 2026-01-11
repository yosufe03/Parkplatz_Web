<?php
include_once("includes/db_connect.php");
include_once("includes/security.php");

// Include header FIRST to start session
$pageTitle = "Login";
include_once("includes/header.php");

// NOW check if already logged in - session is started
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';

if (isset($_GET['locked'])) {
    $message = "Ihr Konto wurde gesperrt. Sie wurden abgemeldet.";
}

if (isset($_GET['tampered'])) {
    $message = "Sicherheitswarnung: Ihr Cookie wurde manipuliert. Bitte melden Sie sich erneut an.";
}

if (isset($_GET['expired'])) {
    $message = "Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF protection
    if (!verify_csrf_token()) {
        $message = "Sicherheitsüberprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.";
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        // Rate limiting to prevent brute force
        if (is_rate_limited('login_attempt', 5, 300)) {
            $message = "Zu viele Anmeldeversuche. Bitte versuchen Sie es in 5 Minuten erneut.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password_hash, role, active FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if($row = $result->fetch_assoc()) {
                if (!$row['active']) {
                    $message = "Ihr Konto wurde gesperrt. Bitte kontaktieren Sie den Support.";
                } elseif(password_verify($password, $row['password_hash'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['last_activity'] = time(); // Set initial activity time

                    // Clear rate limit on successful login
                    clear_rate_limit('login_attempt');

                    // Create remember me cookie if checked
                    if ($remember_me) {
                        $token = $email . hash_hmac('sha256', $email, $config['app_secret_key']);
                        setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    }

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $message = "Ungültige Anmeldedaten.";
                }
            } else {
                $message = "Ungültige Anmeldedaten.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
        }
        .login-card {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>

<body>

<main class="container">
    <div class="login-card bg-white">
        <h2 class="text-center mb-4">Login</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <input class="form-control" type="email" name="email" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <input class="form-control" type="password" name="password" placeholder="Password" required>
            </div>
            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                <label class="form-check-label" for="remember_me">
                    Anmeldedaten merken (30 Tage)
                </label>
            </div>
            <button class="btn btn-primary w-100" type="submit">Login</button>
        </form>
        <?php if($message): ?>
            <p class="text-danger mt-3 text-center"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <p class="mt-3 text-center">
            Noch kein Konto? <a href="register.php">Registrieren</a>
        </p>
    </div>
</main>

</body>
</html>
