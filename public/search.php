<?php
include("includes/db_connect.php");

$location = $_GET['location'] ?? '';

$stmt = $conn->prepare("SELECT * FROM parkings WHERE location LIKE ? AND status='approved'");
$searchTerm = "%$location%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - ParkShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
<h2>Search Results for "<?= htmlspecialchars($location) ?>"</h2>
<a href="dashboard.php">Back to Dashboard</a>
<div class="row mt-3">
    <?php while($row = $result->fetch_assoc()): ?>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                    <p class="card-text"><?= htmlspecialchars($row['description']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($row['location']) ?></p>
                    <p><strong>Price:</strong> €<?= number_format($row['price'],2) ?></p>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
