<?php
session_start();
include("includes/db_connect.php");

$message = '';

// Check if user was logged out due to being locked
if (isset($_GET['locked'])) {
    $message = "Ihr Konto wurde gesperrt. Sie wurden abgemeldet.";
}

// Include header FIRST to check active status before any other operations
$pageTitle = "Login";
include("includes/header.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password_hash, role, active FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()) {
        // Check if user is locked
        if (!$row['active']) {
            $message = "Ihr Konto wurde gesperrt. Bitte kontaktieren Sie den Support.";
        } elseif(password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            header("Location: dashboard.php");
            exit;
        } else {
            $message = "Ungültige Anmeldedaten.";
        }
    } else {
        $message = "Ungültige Anmeldedaten.";
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
            <div class="mb-3">
                <input class="form-control" type="email" name="email" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <input class="form-control" type="password" name="password" placeholder="Password" required>
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
