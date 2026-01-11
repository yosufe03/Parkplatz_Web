<?php
include_once "includes/parking_utils.php";

$pageTitle = "Registrieren";
include "includes/header.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        $message = "Sicherheitsüberprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $username_check = validate_username($username);
        if (!$username_check['valid']) {
            $message = $username_check['message'];
        } elseif (!is_valid_email($email)) {
            $message = "Ungültige Email Adresse.";
        } elseif ($password !== $confirm_password) {
            $message = "Passwörter stimmen nicht überein.";
        } else {
            $password_check = validate_password_strength($password);
            if (!$password_check['valid']) {
                $message = $password_check['message'];
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();

                if ($stmt->get_result()->num_rows > 0) {
                    $message = "Benutzername oder Email existiert bereits.";
                } else {
                    $stmt->close();
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')");
                    $stmt->bind_param("sss", $username, $email, $password_hash);

                    if ($stmt->execute()) {
                        $_SESSION['user_id'] = $conn->insert_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['last_activity'] = time();
                        $stmt->close();
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $message = "Registrierungsfehler. Bitte versuchen Sie es später erneut.";
                        error_log("Registration error: " . $stmt->error);
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<body>
<main class="register-main">
    <div class="register-card">
        <h2>Registrieren</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Benutzername</label>
                <input class="form-control" type="text" id="username" name="username" placeholder="Benutzername (3-50 Zeichen)" required>
                <small class="text-muted">Nur Buchstaben, Zahlen, Unterstriche und Bindestriche</small>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input class="form-control" type="email" id="email" name="email" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Passwort</label>
                <input class="form-control" type="password" id="password" name="password" placeholder="Passwort" required>
                <small class="text-muted d-block mt-2">
                    Anforderungen:
                    <ul class="mb-0 ps-3 password-requirements">
                        <li>Mindestens 8 Zeichen</li>
                        <li>Mindestens einen Großbuchstaben (A-Z)</li>
                        <li>Mindestens einen Kleinbuchstaben (a-z)</li>
                        <li>Mindestens eine Zahl (0-9)</li>
                    </ul>
                </small>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Passwort wiederholen</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="Passwort wiederholen" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Registrieren</button>
        </form>

        <?php if ($message): ?>
            <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <p class="mt-3 text-center">
            Haben Sie bereits ein Konto? <a href="login.php">Login</a>
        </p>
    </div>
</main>

</body>
</html>
