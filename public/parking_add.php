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
$title = $description = "";
$price = "";
$available_from = "";
$available_to = "";

// Determine currently selected district/neighborhood from POST (submit) or GET (refresh)
$district_id = null;
$neighborhood_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['district_id']) && (int)$_POST['district_id'] > 0) $district_id = (int)$_POST['district_id'];
    if (isset($_POST['neighborhood_id']) && (int)$_POST['neighborhood_id'] > 0) $neighborhood_id = (int)$_POST['neighborhood_id'];
} else {
    if (isset($_GET['district_id']) && (int)$_GET['district_id'] > 0) $district_id = (int)$_GET['district_id'];
    if (isset($_GET['neighborhood_id']) && (int)$_GET['neighborhood_id'] > 0) $neighborhood_id = (int)$_GET['neighborhood_id'];
}

// Load districts for selects
$districts = [];
$dstmt = $conn->prepare("SELECT * FROM districts ORDER BY name ASC");
if ($dstmt) {
    $dstmt->execute();
    $dres = $dstmt->get_result();
    while ($d = $dres->fetch_assoc()) $districts[] = $d;
    $dstmt->close();
}

// Load neighborhoods depending on currently selected district (server-side dependent select)
$neighborhoods = [];
if ($district_id !== null && $district_id > 0) {
    $nstmt = $conn->prepare("SELECT * FROM neighborhoods WHERE district_id = ? ORDER BY name ASC");
    if ($nstmt) {
        $nstmt->bind_param('i', $district_id);
        $nstmt->execute();
        $nres = $nstmt->get_result();
        while ($n = $nres->fetch_assoc()) $neighborhoods[] = $n;
        $nstmt->close();
    }
} else {
    $nstmt = $conn->prepare("SELECT * FROM neighborhoods ORDER BY name ASC");
    if ($nstmt) {
        $nstmt->execute();
        $nres = $nstmt->get_result();
        while ($n = $nres->fetch_assoc()) $neighborhoods[] = $n;
        $nstmt->close();
    }
}

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = $_POST['price'] ?? "";

    // area selections (required)
    $district_id = isset($_POST['district_id']) && (int)$_POST['district_id'] > 0 ? (int)$_POST['district_id'] : null;
    $neighborhood_id = isset($_POST['neighborhood_id']) && (int)$_POST['neighborhood_id'] > 0 ? (int)$_POST['neighborhood_id'] : null;

    // Availability (DATE-only)
    $available_from = $_POST['available_from'] ?? "";
    $available_to   = $_POST['available_to'] ?? "";

    // Basic validation
    if ($title === "" || $description === "" || $price === "") {
        $error = "Bitte alle Felder ausfüllen.";
    } elseif ($district_id === null || $neighborhood_id === null) {
        $error = "Bitte Distrikt und Stadtteil auswählen.";
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

        // Insert new parking listing (with optional area refs)
        // Validate that neighborhood belongs to the selected district
        $chk = $conn->prepare("SELECT district_id FROM neighborhoods WHERE id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('i', $neighborhood_id);
            $chk->execute();
            $cres = $chk->get_result();
            if ($cr = $cres->fetch_assoc()) {
                if ((int)$cr['district_id'] !== (int)$district_id) {
                    $error = "Gewählter Stadtteil gehört nicht zum ausgewählten Distrikt.";
                }
            } else {
                $error = "Ungültiger Stadtteil.";
            }
            $chk->close();
        }

        $stmt = $conn->prepare(
            "INSERT INTO parkings (owner_id, title, description, price, district_id, neighborhood_id)
            VALUES (?, ?, ?, ?, ?, ?)"
        );
    $priceFloat = (float)$price;
    $dvar = $district_id;
    $nvar = $neighborhood_id;
    $stmt->bind_param("issdii", $userId, $title, $description, $priceFloat, $dvar, $nvar);

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

        <!-- location field removed: use Distrikt/Stadtteil selects instead -->

        <div class="mb-3">
            <label for="price" class="form-label">Preis (€ pro Tag)</label>
            <input type="number" step="0.01" name="price" id="price" class="form-control" required
                   value="<?= htmlspecialchars($price) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Distrikt</label>
            <div class="d-flex">
                <select name="district_id" class="form-select me-2" required>
                    <option value="">-- auswählen --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (isset($district_id) && $district_id == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- no-JS fallback / GET refresh: reload page to update neighborhoods for selected district -->
                <!-- formnovalidate prevents HTML5 form validation from blocking this refresh button -->
                <button type="submit" formmethod="get" formnovalidate class="btn btn-outline-secondary" title="Stadtteile laden">Aktualisieren</button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Stadtteil</label>
            <select name="neighborhood_id" class="form-select" required>
                <option value="">-- auswählen --</option>
                <?php foreach ($neighborhoods as $n): ?>
                    <option value="<?= (int)$n['id'] ?>" <?= (isset($neighborhood_id) && $neighborhood_id == $n['id']) ? 'selected' : '' ?>><?= htmlspecialchars($n['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Wird benötigt; bitte passenden Distrikt auswählen. Klicken Sie auf "Aktualisieren", um die Liste der Stadtteile für den gewählten Distrikt zu laden.</small>
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

<!-- No JavaScript: use the "Aktualisieren" button to refresh neighborhoods server-side. -->
