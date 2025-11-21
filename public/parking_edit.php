<?php
include("includes/db_connect.php");
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch current user role
$stmtUser = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$currentUser = $resultUser->fetch_assoc();
$isAdmin = $currentUser['role'] === 'admin';

// Capture return URL
$returnUrl = $_SESSION['return_to'] ?? 'my_parkings.php';

$parkingId = $_GET['id'] ?? 0;

// Fetch parking info
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT * FROM parkings WHERE id=?");
    $stmt->bind_param("i", $parkingId);
} else {
    $stmt = $conn->prepare("SELECT * FROM parkings WHERE id=? AND owner_id=?");
    $stmt->bind_param("ii", $parkingId, $userId);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Parking not found or you do not have permission to edit it.");
}

$parking = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_parking'])) {
        $uploadDir = __DIR__ . "/uploads/parkings/$parkingId/";
        if (is_dir($uploadDir)) {
            foreach (glob($uploadDir . "*") as $file) {
                unlink($file);
            }
            rmdir($uploadDir);
        }

        if ($isAdmin) {
            $stmtDel = $conn->prepare("DELETE FROM parkings WHERE id=?");
            $stmtDel->bind_param("i", $parkingId);
        } else {
            $stmtDel = $conn->prepare("DELETE FROM parkings WHERE id=? AND owner_id=?");
            $stmtDel->bind_param("ii", $parkingId, $userId);
        }
        $stmtDel->execute();
        header("Location: $returnUrl");
        exit;
    }

    // Collect fields
    $title = $_POST['title'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $price = $_POST['price'];

    // Admin can also edit owner_id and status
    if ($isAdmin) {
        $ownerId = $_POST['owner_id'];
        $status = $_POST['status'];
        $stmtUpdate = $conn->prepare("UPDATE parkings SET title=?, description=?, location=?, price=?, owner_id=?, status=? WHERE id=?");
        $stmtUpdate->bind_param("sssdisi", $title, $description, $location, $price, $ownerId, $status, $parkingId);
    } else {
        $stmtUpdate = $conn->prepare("UPDATE parkings SET title=?, description=?, location=?, price=? WHERE id=? AND owner_id=?");
        $stmtUpdate->bind_param("sssdii", $title, $description, $location, $price, $parkingId, $userId);
    }
    $stmtUpdate->execute();

    // Handle image uploads as before...
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . "/uploads/parkings/$parkingId/";
        mkdir($uploadDir, 0755, true);

        $existingImages = glob($uploadDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
        $existingNumbers = [];
        foreach ($existingImages as $img) {
            if (preg_match('/(\d+)\.(jpg|jpeg|png)$/i', basename($img), $matches)) {
                $existingNumbers[] = (int)$matches[1];
            }
        }
        $nextNumber = $existingNumbers ? max($existingNumbers) + 1 : 1;

        foreach ($_FILES['images']['tmp_name'] as $tmpIndex => $tmpName) {
            $ext = pathinfo($_FILES['images']['name'][$tmpIndex], PATHINFO_EXTENSION);
            $filename = $nextNumber . "." . $ext;
            move_uploaded_file($tmpName, $uploadDir . $filename);
            if (empty($parking['main_image'])) {
                $stmt2 = $conn->prepare("UPDATE parkings SET main_image=? WHERE id=?");
                $stmt2->bind_param("si", $filename, $parkingId);
                $stmt2->execute();
            }
            $nextNumber++;
        }
    }

    header("Location: $returnUrl");
    exit;
}

// Fetch images
$imageDir = "uploads/parkings/$parkingId/";
$images = glob($imageDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
sort($images);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Parkplatz bearbeiten - <?= htmlspecialchars($parking['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .parking-img { width: 100%; height: 150px; object-fit: cover; border-radius: 5px; }
        .img-card { position: relative; }
        .delete-btn { position: absolute; top: 5px; right: 5px; }
    </style>
</head>
<body>
<?php include("includes/header.php"); ?>

<div class="container mt-5">
    <h2>Parkplatz bearbeiten</h2>

    <form method="POST" enctype="multipart/form-data" class="mt-4">
        <div class="mb-3">
            <label class="form-label">Titel</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($parking['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($parking['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Ort</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($parking['location']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Preis (€ pro Tag)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($parking['price']) ?>" required>
        </div>

        <?php if ($isAdmin): ?>
            <div class="mb-3">
                <label class="form-label">Besitzer ID</label>
                <input type="number" name="owner_id" class="form-control" value="<?= htmlspecialchars($parking['owner_id']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="pending" <?= $parking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $parking['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $parking['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Neue Bilder hochladen</label>
            <input type="file" name="images[]" class="form-control" multiple accept="image/jpeg,image/png,image/jpg">
            <small class="text-muted">Neue Bilder werden zu bestehenden Bildern hinzugefügt.</small>
        </div>

        <button type="submit" class="btn btn-success">Speichern</button>
        <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-secondary">Abbrechen</a>
        <button type="submit" name="delete_parking" class="btn btn-danger float-end"
                onclick="return confirm('Willst du diesen Parkplatz wirklich löschen?');">
            Parkplatz löschen
        </button>
    </form>

    <hr class="my-5">

    <h4>Bestehende Bilder</h4>
    <div class="row mt-3">
        <?php foreach ($images as $img): ?>
            <div class="col-md-3 mb-3 img-card">
                <img src="<?= htmlspecialchars($img) ?>" class="parking-img">
                <a href="?id=<?= $parkingId ?>&delete_img=<?= basename($img) ?>&return=<?= urlencode($returnUrl) ?>"
                   class="btn btn-danger btn-sm delete-btn">X</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
