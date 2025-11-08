<?php
session_start();
include("includes/db_connect.php");

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    echo password_hash('password', PASSWORD_DEFAULT) . "\n";


    if($row = $result->fetch_assoc()) {
        if(password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            header("Location: dashboard.php");
            exit;
        }
    }
    $message = "Invalid credentials.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - ParkShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
<h2>Login</h2>
<form method="POST">
    <input class="form-control mb-2" type="email" name="email" placeholder="Email" required>
    <input class="form-control mb-2" type="password" name="password" placeholder="Password" required>
    <button class="btn btn-primary" type="submit">Login</button>
</form>
<?php if($message) echo "<p class='text-danger mt-2'>$message</p>"; ?>
</body>
</html>
