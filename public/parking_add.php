<?php
session_start();
include("includes/db_connect.php");
include_once __DIR__ . '/includes/parking_utils.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');
$error = "";

// Load draft values if exists
$title = $description = $price = $available_from = $available_to = "";
$district_id = $neighborhood_id = null;
$images = [];

$draftId = $_SESSION['draft_parking_id'] ?? null;
error_log("LOAD: draftId from session=$draftId, REQUEST_METHOD=" . $_SERVER['REQUEST_METHOD']);

if ($draftId) {
    $stmt = $conn->prepare("SELECT p.*, pa.available_from, pa.available_to 
                            FROM parkings p 
                            LEFT JOIN parking_availability pa ON p.id = pa.parking_id 
                            WHERE p.id = ? LIMIT 1");
    $stmt->bind_param('i', $draftId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $title = $row['title'] ?? '';
        $description = $row['description'] ?? '';
        $price = $row['price'] ?? '';
        $district_id = $row['district_id'] ?? null;
        $neighborhood_id = $row['neighborhood_id'] ?? null;
        $available_from = $row['available_from'] ?: '';
        $available_to = $row['available_to'] ?: '';
        $images = get_image_files($draftId);
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Override loaded values with POST values
    $title = trim($_POST['title'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = $_POST['price'] ?? "";
    $district_id = (int)($_POST['district_id'] ?? 0) ?: null;
    $neighborhood_id = (int)($_POST['neighborhood_id'] ?? 0) ?: null;
    $available_from = $_POST['available_from'] ?? "";
    $available_to = $_POST['available_to'] ?? "";

    // Refresh button - save draft and reload
    if (isset($_POST['refresh'])) {
        error_log("BEFORE save_draft: draftId=$draftId, session=" . ($_SESSION['draft_parking_id'] ?? 'null'));
        $draftId = save_draft($conn, $userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $draftId);
        $_SESSION['draft_parking_id'] = $draftId;
        error_log("AFTER save_draft: draftId=$draftId, session=" . $_SESSION['draft_parking_id']);

        header('Location: parking_add.php');
        exit;
    }

    // Final submit - validate and save
    if (!$title || !$description || !$price) {
        $error = "Bitte alle Felder ausfüllen.";
    } elseif (!$district_id || !$neighborhood_id) {
        $error = "Bitte Distrikt und Stadtteil auswählen.";
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = "Bitte einen gültigen Preis eingeben.";
    } elseif (!isValidDate($available_from) || !isValidDate($available_to)) {
        $error = "Bitte gültige Verfügbarkeitsdaten auswählen.";
    } elseif ($available_from < $today || $available_to < $today) {
        $error = "Verfügbarkeit darf nicht in der Vergangenheit liegen.";
    } elseif ($available_to < $available_from) {
        $error = "Das Enddatum muss nach dem Startdatum liegen.";
    } else {
        // Check neighborhood belongs to district
        $stmt = $conn->prepare("SELECT district_id FROM neighborhoods WHERE id = ?");
        $stmt->bind_param('i', $neighborhood_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$row || (int)$row['district_id'] !== $district_id) {
            $error = "Gewählter Stadtteil gehört nicht zum ausgewählten Distrikt.";
        } else {
            // Check image count
            $existingImages = $draftId ? get_image_files($draftId) : [];
            $newUploads = !empty($_FILES['images']['tmp_name']) ? count(array_filter($_FILES['images']['tmp_name'])) : 0;
            
            if (count($existingImages) + $newUploads === 0) {
                $error = "Bitte mindestens ein Bild hochladen.";
            } elseif (count($existingImages) + $newUploads > 5) {
                $error = "Maximal 5 Bilder erlaubt.";
            }
        }
    }

    if (!$error) {
        $priceFloat = (float)$price;
        
        // Save parking
        if ($draftId) {
            $stmt = $conn->prepare("UPDATE parkings SET title=?, description=?, price=?, district_id=?, neighborhood_id=?, status='pending' WHERE id=? AND owner_id=?");
            $stmt->bind_param("ssdiiii", $title, $description, $priceFloat, $district_id, $neighborhood_id, $draftId, $userId);
            $parkingId = $draftId;
        } else {
            $stmt = $conn->prepare("INSERT INTO parkings (owner_id, title, description, price, district_id, neighborhood_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issdii", $userId, $title, $description, $priceFloat, $district_id, $neighborhood_id);
            $parkingId = 0;
        }
        $stmt->execute();
        if (!$draftId) $parkingId = (int)$stmt->insert_id;
        $stmt->close();

        // Save availability
        $conn->query("DELETE FROM parking_availability WHERE parking_id = $parkingId");
        $stmt = $conn->prepare("INSERT INTO parking_availability (parking_id, available_from, available_to) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $parkingId, $available_from, $available_to);
        $stmt->execute();
        $stmt->close();

        // Handle uploads
        if (!empty($_FILES['images']['tmp_name'])) {
            $uploadDir = get_upload_dir($parkingId);
            ensure_dir($uploadDir);
            
            $maxNum = 0;
            foreach (glob($uploadDir . "*.{jpg,jpeg,png}", GLOB_BRACE) ?: [] as $img) {
                if (preg_match('/(\d+)\./i', basename($img), $m)) $maxNum = max($maxNum, (int)$m[1]);
            }
            
            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                if (!$tmp || !is_uploaded_file($tmp) || $maxNum >= 5) continue;
                $ext = strtolower(pathinfo($_FILES['images']['name'][$idx], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png'])) {
                    move_uploaded_file($tmp, $uploadDir . ++$maxNum . "." . $ext);
                }
            }
        }

        unset($_SESSION['draft_parking_id']);
        header("Location: my_parkings.php");
        exit;
    }
} else {
    // Handle GET - preserve query params if present
    if (!empty($_GET['district_id'])) $district_id = (int)$_GET['district_id'] ?: $district_id;
    if (!empty($_GET['neighborhood_id'])) $neighborhood_id = (int)$_GET['neighborhood_id'] ?: $neighborhood_id;
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

    <?php if ($error): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['upload_error'])): ?>
        <div class="alert alert-warning mt-3"><?= htmlspecialchars($_SESSION['upload_error']) ?></div>
        <?php unset($_SESSION['upload_error']); ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="mt-4">
        <?php
        $submitLabel = 'Parkplatz hinzufügen';
        include __DIR__ . '/includes/parking_form.php';
        ?>

    </form>
</div>

</body>
</html>

