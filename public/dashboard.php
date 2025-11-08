<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include("includes/db_connect.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - ParkShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
<h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
<p><a href="logout.php">Logout</a></p>

<h3>Search Parking</h3>
<form method="GET" action="search.php" class="row g-3 mb-3">
    <div class="col-md-4"><input class="form-control" name="location" placeholder="Location"></div>
    <div class="col-md-2"><button class="btn btn-primary" type="submit">Search</button></div>
</form>
</body>
</html>

