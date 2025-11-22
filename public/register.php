<?php
session_start();
include("includes/db_connect.php");

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param("sss", $username, $email, $password_hash);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';
            header("Location: dashboard.php");
            exit;
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<?php
    $pageTitle = "Register";
    include("includes/header.php");
?>

<style>
    body {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        background-color: #f8f9fa;
    }

    main {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1rem;
    }

    .register-card {
        width: 100%;
        max-width: 360px; /* Mobile-first width */
        padding: 2rem 1.5rem;
        background-color: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    @media(min-width: 768px) {
        .register-card {
            max-width: 450px; /* Larger screens */
            padding: 2.5rem 2rem;
        }
    }

    h2 {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .form-control {
        padding: 0.625rem 0.75rem;
        font-size: 0.95rem;
    }

    .btn-primary {
        padding: 0.6rem 0.75rem;
        font-size: 1rem;
    }

    p.text-center a {
        text-decoration: none;
    }
</style>
<body>

<main>
    <div class="register-card">
        <h2>Register</h2>
        <form method="POST">
            <div class="mb-3">
                <input class="form-control" type="text" name="username" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input class="form-control" type="email" name="email" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <input class="form-control" type="password" name="password" placeholder="Password" required>
            </div>
            <div class="mb-3">
                <input class="form-control" type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Register</button>
        </form>

        <?php if($message): ?>
            <p class="text-danger mt-3 text-center"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <p class="mt-3 text-center">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</main>

</body>
</html>
