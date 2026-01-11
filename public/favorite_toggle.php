<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once "includes/parking_utils.php";

$parkingId = (int)($_POST['parking_id'] ?? 0);
if ($parkingId <= 0) {
    header('Location: index.php');
    exit;
}

toggle_favorite($parkingId, (int)$_SESSION['user_id'], $_POST['action'] ?? 'add');

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
