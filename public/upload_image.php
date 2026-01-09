<?php
session_start();
include __DIR__ . '/includes/db_connect.php';
include __DIR__ . '/includes/parking_utils.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// First, save any form data that was submitted along with the upload
if (isset($_POST['title']) || isset($_POST['description']) || isset($_POST['price'])) {
    $draftId = save_draft(
        $conn,
        $userId,
        trim($_POST['title'] ?? ''),
        trim($_POST['description'] ?? ''),
        $_POST['price'] ?? '',
        (int)($_POST['district_id'] ?? 0) ?: null,
        (int)($_POST['neighborhood_id'] ?? 0) ?: null,
        $_POST['available_from'] ?? '',
        $_POST['available_to'] ?? '',
        $_SESSION['draft_parking_id'] ?? null
    );
    $_SESSION['draft_parking_id'] = $draftId;
}

// We no longer rely on a return_to param; when a parking/draft id exists we redirect
// to the edit page for that parking so the form values are persisted in the DB.

// Determine parking id from session draft or POST
$parkingId = isset($_POST['id']) && (int)$_POST['id'] > 0 ? (int)$_POST['id'] : ($_SESSION['draft_parking_id'] ?? null);

// Always return to parking_add.php since editing is disabled
$returnUrl = 'parking_add.php';

if (!$parkingId) {
    // No draft exists - redirect back to add page with message
    $_SESSION['upload_error'] = 'Bitte zuerst "Aktualisieren" klicken, um einen Entwurf zu speichern.';
    header('Location: parking_add.php');
    exit;
}

// No sticky QS helper: draft records persist the input values server-side.

// Handle delete-existing-file action
if (isset($_POST['delete_existing_file']) && $_POST['delete_existing_file'] !== '') {
    $del = basename($_POST['delete_existing_file']);
    if ($parkingId) {
        $p = get_upload_dir($parkingId) . $del;
        if (is_file($p)) @unlink($p);
    }
    header("Location: $returnUrl");
    exit;
}

// Handle per-slot upload
if (isset($_POST['upload_slot'])) {
    $slot = (int)$_POST['upload_slot'];
    if ($parkingId) {
        $uploadDir = get_upload_dir($parkingId);
        ensure_dir($uploadDir);

        // compute next number
        $existing = glob($uploadDir . "*.{jpg,jpeg,png}", GLOB_BRACE) ?: [];
        $nums = [];
        foreach ($existing as $t) {
            if (preg_match('/(\\d+)\\.(jpg|jpeg|png)$/i', basename($t), $m)) $nums[] = (int)$m[1];
        }
        $next = $nums ? max($nums) + 1 : 1;

        if (isset($_FILES['images']) && isset($_FILES['images']['tmp_name'][$slot]) && is_uploaded_file($_FILES['images']['tmp_name'][$slot])) {
            $tmpName = $_FILES['images']['tmp_name'][$slot];
            $originalName = $_FILES['images']['name'][$slot] ?? '';
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png'])) {
                $dest = $uploadDir . $next . '.' . $ext;
                move_uploaded_file($tmpName, $dest);
            }
        }
    }

    header("Location: $returnUrl");
    exit;
}

// nothing matched — redirect back
header("Location: $returnUrl");
exit;

?>
