<?php
include("includes/db_connect.php");
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $price = $_POST['price'];

    // Insert new parking listing
    $stmt = $conn->prepare("INSERT INTO parkings (owner_id, title, description, location, price, status) VALUES (?, ?, ?, ?, ?, 'approved')");
    $stmt->bind_param("isssd", $userId, $title, $description, $location, $price);
    $stmt->execute();
    $parkingId = $stmt->insert_id;

    // Handle file uploads
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . "/uploads/parkings/$parkingId/";
        mkdir($uploadDir, 0755, true);

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
            $ext = pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION);
            $filename = ($index + 1) . "." . $ext; // 1.jpg, 2.jpg, etc.
            move_uploaded_file($tmpName, $uploadDir . $filename);

            // Save first image as main_image in DB
            if ($index === 0) {
                $stmt2 = $conn->prepare("UPDATE parkings SET main_image=? WHERE id=?");
                $stmt2->bind_param("si", $filename, $parkingId);
                $stmt2->execute();
            }
        }
    }

    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neuen Parkplatz hinzufügen - ParkShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include("includes/header.php"); ?>

<div class="container mt-5">
    <h2>Neuen Parkplatz hinzufügen</h2>

    <form method="POST" enctype="multipart/form-data" class="mt-4">
        <div class="mb-3">
            <label for="title" class="form-label">Titel</label>
            <input type="text" name="title" id="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Beschreibung</label>
            <textarea name="description" id="description" class="form-control" rows="4" required></textarea>
        </div>

        <div class="mb-3">
            <label for="location" class="form-label">Ort</label>
            <input type="text" name="location" id="location" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Preis (€ pro Tag)</label>
            <input type="number" step="0.01" name="price" id="price" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="images" class="form-label">Bilder hochladen</label>
            <input type="file" name="images[]" id="images" class="form-control" multiple accept="image/jpeg,image/png">
            <small class="text-muted">Hauptbild sollte als erstes ausgewählt werden. Max 5 Bilder, JPG/PNG.</small>
        </div>

        <button type="submit" class="btn btn-success">Parkplatz hinzufügen</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

