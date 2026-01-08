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

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

// Fetch current user role
$stmtUser = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$currentUser = $resultUser->fetch_assoc();
$stmtUser->close();

$isAdmin = ($currentUser && $currentUser['role'] === 'admin');

// Capture return URL
$returnUrl = $_SESSION['return_to'] ?? 'my_parkings.php';

$parkingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($parkingId <= 0) {
    die("Invalid parking id.");
}

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
$stmt->close();

// Load districts and neighborhoods for selects
$districts = [];
$neighborhoods = [];
$dstmt = $conn->prepare("SELECT * FROM districts ORDER BY name ASC");
if ($dstmt) {
    $dstmt->execute();
    $dres = $dstmt->get_result();
    while ($d = $dres->fetch_assoc()) $districts[] = $d;
    $dstmt->close();
}

$nstmt = $conn->prepare("SELECT * FROM neighborhoods ORDER BY name ASC");
if ($nstmt) {
    $nstmt->execute();
    $nres = $nstmt->get_result();
    while ($n = $nres->fetch_assoc()) $neighborhoods[] = $n;
    $nstmt->close();
}

/* ---------- NEW: Fetch current availability (single row) ---------- */
$avail_from = "";
$avail_to = "";

$stmtAvail = $conn->prepare("
    SELECT available_from, available_to
    FROM parking_availability
    WHERE parking_id = ?
    LIMIT 1
");
$stmtAvail->bind_param("i", $parkingId);
$stmtAvail->execute();
$resAvail = $stmtAvail->get_result();
if ($resAvail->num_rows > 0) {
    $a = $resAvail->fetch_assoc();
    $avail_from = $a['available_from']; // DATE
    $avail_to   = $a['available_to'];   // DATE
}
$stmtAvail->close();

/* Optional: allow deleting an image via GET (your template already links to it)
   Your original code didn't implement it; leaving untouched to avoid side effects. */

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
        $stmtDel->close();

        header("Location: $returnUrl");
        exit;
    }

    // Collect fields
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $district_id = isset($_POST['district_id']) && (int)$_POST['district_id'] > 0 ? (int)$_POST['district_id'] : null;
    $neighborhood_id = isset($_POST['neighborhood_id']) && (int)$_POST['neighborhood_id'] > 0 ? (int)$_POST['neighborhood_id'] : null;

    /* ---------- NEW: Collect availability (DATE only) ---------- */
    $avail_from_post = $_POST['available_from'] ?? '';
    $avail_to_post   = $_POST['available_to'] ?? '';

    // Basic validation for availability
    if (!isValidDate($avail_from_post) || !isValidDate($avail_to_post)) {
        die("Invalid availability dates.");
    }
    if ($avail_from_post < $today || $avail_to_post < $today) {
        die("Availability cannot be in the past.");
    }
    if ($avail_to_post < $avail_from_post) {
        die("Availability end date must be after start date.");
    }

    // Require district and neighborhood
    if ($district_id === null || $neighborhood_id === null) {
        die("Bitte Distrikt und Stadtteil auswählen.");
    }

    // Validate neighborhood belongs to district
    $chk = $conn->prepare("SELECT district_id FROM neighborhoods WHERE id = ? LIMIT 1");
    if ($chk) {
        $chk->bind_param('i', $neighborhood_id);
        $chk->execute();
        $cres = $chk->get_result();
        if ($cr = $cres->fetch_assoc()) {
            if ((int)$cr['district_id'] !== (int)$district_id) {
                die("Gewählter Stadtteil gehört nicht zum ausgewählten Distrikt.");
            }
        } else {
            die("Ungültiger Stadtteil.");
        }
        $chk->close();
    }

    // Admin can also edit owner_id and status
    if ($isAdmin) {
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';

        $stmtUpdate = $conn->prepare(" 
            UPDATE parkings
            SET title=?, description=?, price=?, owner_id=?, status=?, district_id=?, neighborhood_id=?
            WHERE id=?
        ");
        $priceFloat = (float)$price;
        $dvar = $district_id !== null ? $district_id : 0;
        $nvar = $neighborhood_id !== null ? $neighborhood_id : 0;
        $stmtUpdate->bind_param("ssdisiii", $title, $description, $priceFloat, $ownerId, $status, $dvar, $nvar, $parkingId);
    } else {
        $stmtUpdate = $conn->prepare(" 
            UPDATE parkings
            SET title=?, description=?, price=?, district_id=?, neighborhood_id=?
            WHERE id=? AND owner_id=?
        ");
        $priceFloat = (float)$price;
        $dvar = $district_id !== null ? $district_id : 0;
        $nvar = $neighborhood_id !== null ? $neighborhood_id : 0;
        $stmtUpdate->bind_param("sssdiiii", $title, $description, $priceFloat, $dvar, $nvar, $parkingId, $userId);
    }
    $stmtUpdate->execute();
    $stmtUpdate->close();

    /* ---------- NEW: Upsert availability (requires UNIQUE(parking_id)) ---------- */
    $stmtUpdAvail = $conn->prepare("
    UPDATE parking_availability
    SET available_from = ?, available_to = ?
    WHERE parking_id = ?
");
    $stmtUpdAvail->bind_param("ssi", $avail_from_post, $avail_to_post, $parkingId);
    $stmtUpdAvail->execute();
    $stmtUpdAvail->close();


    // Handle image uploads as before...
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . "/uploads/parkings/$parkingId/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $existingImages = glob($uploadDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
        $existingNumbers = [];
        foreach ($existingImages as $img) {
            if (preg_match('/(\d+)\.(jpg|jpeg|png)$/i', basename($img), $matches)) {
                $existingNumbers[] = (int)$matches[1];
            }
        }
        $nextNumber = $existingNumbers ? max($existingNumbers) + 1 : 1;

        foreach ($_FILES['images']['tmp_name'] as $tmpIndex => $tmpName) {
            if ($tmpName === "" || !is_uploaded_file($tmpName)) continue;

            $ext = strtolower(pathinfo($_FILES['images']['name'][$tmpIndex], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;

            $filename = $nextNumber . "." . $ext;
            move_uploaded_file($tmpName, $uploadDir . $filename);

            if (empty($parking['main_image'])) {
                $stmt2 = $conn->prepare("UPDATE parkings SET main_image=? WHERE id=?");
                $stmt2->bind_param("si", $filename, $parkingId);
                $stmt2->execute();
                $stmt2->close();
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
<?php
$pageTitle = "Parkplatz bearbeiten";
include("includes/header.php");
?>

<style>
    .parking-img { width: 100%; height: 150px; object-fit: cover; border-radius: 5px; }
    .img-card { position: relative; }
    .delete-btn { position: absolute; top: 5px; right: 5px; }
</style>
<body>

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

        <!-- location removed: use Distrikt / Stadtteil selects -->

        <div class="mb-3">
            <label class="form-label">Preis (€ pro Tag)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($parking['price']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Distrikt</label>
            <select name="district_id" class="form-select" required>
                <option value="">-- auswählen --</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (isset($parking['district_id']) && $parking['district_id'] == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Stadtteil</label>
            <select name="neighborhood_id" class="form-select" required>
                <option value="">-- auswählen --</option>
                <?php foreach ($neighborhoods as $n): ?>
                    <option value="<?= (int)$n['id'] ?>" <?= (isset($parking['neighborhood_id']) && $parking['neighborhood_id'] == $n['id']) ? 'selected' : '' ?>><?= htmlspecialchars($n['name']) ?> (<?= htmlspecialchars($n['district_id']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- NEW: Availability (single, date only) -->
        <div class="mb-3">
            <label class="form-label">Verfügbarkeit (Datum)</label>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Von</label>
                    <input type="date" name="available_from" class="form-control"
                           value="<?= htmlspecialchars($avail_from) ?>"
                           min="<?= htmlspecialchars($today) ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bis</label>
                    <input type="date" name="available_to" class="form-control"
                           value="<?= htmlspecialchars($avail_to) ?>"
                           min="<?= htmlspecialchars($today) ?>"
                           required>
                </div>
            </div>
            <small class="text-muted">Nur zukünftige Daten. Enddatum muss ≥ Startdatum sein.</small>
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
                <a href="?id=<?= (int)$parkingId ?>&delete_img=<?= basename($img) ?>&return=<?= urlencode($returnUrl) ?>"
                   class="btn btn-danger btn-sm delete-btn">X</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
