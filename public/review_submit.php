<?php
session_start();
include("includes/db_connect.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    // not logged in
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$parking_id = isset($_POST['parking_id']) ? (int)$_POST['parking_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;

$errors = [];
if ($parking_id <= 0) $errors[] = 'Invalid parking id.';
if ($rating < 1 || $rating > 5) $errors[] = 'Rating must be between 1 and 5.';
if ($comment !== null && mb_strlen($comment) > 500) $errors[] = 'Comment too long.';

if (!empty($errors)) {
    $qs = 'id=' . urlencode($parking_id) . '&review_status=error';
    header('Location: parking.php?' . $qs);
    exit;
}

// Use INSERT ... ON DUPLICATE KEY UPDATE to upsert
$stmt = $conn->prepare(
    "INSERT INTO parking_reviews (parking_id, user_id, rating, comment) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP"
);
$stmt->bind_param('iiis', $parking_id, $user_id, $rating, $comment);

if (!$stmt->execute()) {
    $stmt->close();
    header('Location: parking.php?id=' . urlencode($parking_id) . '&review_status=error');
    exit;
}

$stmt->close();

header('Location: parking.php?id=' . urlencode($parking_id) . '&review_status=ok');
exit;

?>
