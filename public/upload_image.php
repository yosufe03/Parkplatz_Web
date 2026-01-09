<?php
session_start();
include __DIR__ . '/includes/parking_utils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$parkingId = (int)($_POST['id'] ?? 0) ?: null;

// Save draft if uploading
if (isset($_POST['save_draft_btn']) && isset($_POST['title'])) {
    $parkingId = save_draft_parking($userId,
        trim($_POST['title'] ?? ''),
        trim($_POST['description'] ?? ''),
        $_POST['price'] ?? '',
        (int)($_POST['district_id'] ?? 0) ?: null,
        (int)($_POST['neighborhood_id'] ?? 0) ?: null,
        $_POST['available_from'] ?? '',
        $_POST['available_to'] ?? '',
        $parkingId
    );
}

// Delete image
if (isset($_POST['delete_existing_file'])) {
    if ($parkingId) {
        $p = get_upload_dir($parkingId) . basename($_POST['delete_existing_file']);
        if (is_file($p)) @unlink($p);
    }
}

// Upload image
if (isset($_POST['save_draft_btn']) && $parkingId) {
    $slot = (int)$_POST['save_draft_btn'];
    if (isset($_FILES['images']['tmp_name'][$slot]) && is_uploaded_file($_FILES['images']['tmp_name'][$slot])) {
        $uploadDir = get_upload_dir($parkingId);
        ensure_dir($uploadDir);

        $existing = glob($uploadDir . "*.{jpg,jpeg,png}", GLOB_BRACE) ?: [];
        $maxNum = 0;
        foreach ($existing as $f) {
            if (preg_match('/(\\d+)\\./i', basename($f), $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }

        $ext = strtolower(pathinfo($_FILES['images']['name'][$slot], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            move_uploaded_file($_FILES['images']['tmp_name'][$slot], $uploadDir . (++$maxNum) . '.' . $ext);
        }
    }
}

// Redirect
$returnUrl = $parkingId ? 'parking_edit.php?id=' . $parkingId : 'parking_add.php';
header('Location: ' . $returnUrl);
exit;

?>
