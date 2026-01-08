<?php
session_start();
include("includes/db_connect.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$parking_id = isset($_POST['parking_id']) ? (int)$_POST['parking_id'] : 0;
$action = $_POST['action'] ?? 'toggle'; // 'add'|'remove'|'toggle'

if ($parking_id <= 0) {
    header('Location: index.php');
    exit;
}

if ($action === 'remove') {
    $d = $conn->prepare("DELETE FROM favorites WHERE parking_id = ? AND user_id = ?");
    $d->bind_param('ii', $parking_id, $user_id);
    $d->execute();
    $d->close();
} else {
    // insert ignore to avoid duplicate
    $i = $conn->prepare("INSERT IGNORE INTO favorites (parking_id, user_id) VALUES (?, ?)");
    $i->bind_param('ii', $parking_id, $user_id);
    $i->execute();
    $i->close();
}

// Redirect back to referrer or parking page
$ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $ref);
exit;
