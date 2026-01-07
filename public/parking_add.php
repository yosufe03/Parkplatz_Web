<?php
session_start();
include("includes/db_connect.php");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

$error = "";

// Sticky values
$title = $description = $location = "";
$price = "";
$available_from = "";
$available_to = "";

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $location = trim($_POST['location'] ?? "");
    $price = $_POST['price'] ?? "";

    // Availability (DATE-only)
    $available_from = $_POST['available_from'] ?? "";
    $available_to   = $_POST['available_to'] ?? "";

    // Basic validation
    if ($title === "" || $description === "" || $location === "" || $price === "") {
        $error = "Bitte alle Felder ausfüllen.";
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = "Bitte einen gültigen Preis eingeben.";
    } elseif (!isValidDate($available_from) || !isValidDate($available_to)) {
        $error = "Bitte gültige Verfügbarkeitsdaten auswählen.";
    } elseif ($available_from < $today || $available_to < $today) {
        $error = "Verfügbarkeit darf nicht in der Vergangenheit liegen.";
    } elseif ($available_to < $available_from) {
        $error = "Das Enddatum muss nach dem Startdatum liegen.";
    }

    // If OK -> insert parking + availability + images
    if ($error === "") {

        // Insert new parking listing
        $stmt = $conn->prepare("
            INSERT INTO parkings (owner_id, title, description, location, price)
            VALUES (?, ?, ?, ?, ?)
        ");
        $priceFloat = (float)$price;
        $stmt->bind_param("isssd", $userId, $title, $description, $location, $priceFloat);

        if (!$stmt->execute()) {
            $error = "Fehler beim Speichern des Parkplatzes.";
        } else {
            $parkingId = (int)$stmt->insert_id;
        }
        $stmt->close();

        // Insert availability (DATE-only)
        if ($error === "") {
            $a = $conn->prepare("
                INSERT INTO parking_availability (parking_id, available_from, available_to)
                VALUES (?, ?, ?)
            ");
            $a->bind_param("iss", $parkingId, $available_from, $available_to);

            if (!$a->execute()) {
                $error = "Fehler beim Speichern der Verfügbarkeit.";
            }
            $a->close();
        }

        // Handle file uploads (your existing logic)
        if ($error === "" && !empty($_FILES['images']['name'][0])) {

            $uploadDir = __DIR__ . "/uploads/parkings/$parkingId/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                if ($tmpName === "" || !is_uploaded_file($tmpName)) continue;

                $originalName = $_FILES['images']['name'][$index];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                // Allow only jpg/jpeg/png
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;

                $filename = ($index + 1) . "." . $ext; // 1.jpg, 2.jpg, etc.
                move_uploaded_file($tmpName, $uploadDir . $filename);

                // Save first image as main_image in DB
                if ($index === 0) {
                    $stmt2 = $conn->prepare("UPDATE parkings SET main_image=? WHERE id=?");
                    $stmt2->bind_param("si", $filename, $parkingId);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }

        // Success redirect
        if ($error === "") {
            header("Location: dashboard.php?added=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<?php
$pageTitle = "Neuer Parkplatz";
include("includes/header.php");
?>

<body>
<div class="container mt-5">
    <h2>Neuen Parkplatz hinzufügen</h2>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="mt-4">

        <div class="mb-3">
            <label for="title" class="form-label">Titel</label>
            <input type="text" name="title" id="title" class="form-control" required
                   value="<?= htmlspecialchars($title) ?>">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Beschreibung</label>
            <textarea name="description" id="description" class="form-control" rows="4" required><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="location" class="form-label">Ort</label>
            <input type="text" name="location" id="location" class="form-control" required
                   value="<?= htmlspecialchars($location) ?>">
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Preis (€ pro Tag)</label>
            <input type="number" step="0.01" name="price" id="price" class="form-control" required
                   value="<?= htmlspecialchars($price) ?>">
        </div>

        <!-- NEW: Availability (DATE-only) -->
        <div class="mb-3">
            <label class="form-label">Verfügbarkeit (Datum)</label>
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="available_from" class="form-label">Von</label>
                    <input type="date" name="available_from" id="available_from"
                           class="form-control" required min="<?= htmlspecialchars($today) ?>"
                           value="<?= htmlspecialchars($available_from) ?>">
                </div>
                <div class="col-md-6">
                    <label for="available_to" class="form-label">Bis</label>
                    <input type="date" name="available_to" id="available_to"
                           class="form-control" required min="<?= htmlspecialchars($today) ?>"
                           value="<?= htmlspecialchars($available_to) ?>">
                </div>
            </div>
            <small class="text-muted">Nur zukünftige Daten. Enddatum muss ≥ Startdatum sein.</small>
        </div>

        <div class="mb-3">
            <label for="images" class="form-label">Bilder hochladen</label>
            <input type="file" name="images[]" id="images" class="form-control" multiple accept="image/jpeg,image/png">
            <small class="text-muted">Hauptbild sollte als erstes ausgewählt werden. Max 5 Bilder, JPG/PNG.</small>
        </div>

        <button type="submit" class="btn btn-success">Parkplatz hinzufügen</button>
    </form>
</div>

</body>
</html>
