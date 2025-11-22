<?php
session_start();
include("includes/db_connect.php");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Check if user is admin
$stmtUser = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$currentUser = $resultUser->fetch_assoc();

if ($currentUser['role'] !== 'admin') {
    die("Zugriff verweigert.");
}

// Handle approve/reject actions
if (isset($_GET['action'], $_GET['id'])) {
    $action = strtolower($_GET['action']); // ensure lowercase
    $id = (int)$_GET['id'];

    // Map URL action to ENUM values
    $statusMap = ['approve' => 'approved', 'reject' => 'rejected'];

    if (array_key_exists($action, $statusMap)) {
        $status = $statusMap[$action];

        $stmt = $conn->prepare("UPDATE parkings SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        header("Location: pending_parkings.php");
        exit;
    } else {
        die("Ungültige Aktion.");
    }
}

// Fetch all pending parkings
$stmt = $conn->prepare("
    SELECT p.*, u.username AS owner_name 
    FROM parkings p 
    LEFT JOIN users u ON p.owner_id = u.id 
    WHERE p.status='pending'
    ORDER BY p.id DESC
");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="de">
<?php
    $pageTitle = "Parkplätze freigeben";
    include("includes/header.php");
?>

<body>

<div class="container mt-5">
    <h2>Parkplätze freigeben</h2>

    <?php if ($result->num_rows === 0): ?>
        <p>Keine ausstehenden Parkplätze.</p>
    <?php else: ?>
        <div class="list-group mt-3">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($row['title']) ?></strong> — <?= htmlspecialchars($row['location']) ?>
                        <small class="text-muted">(Besitzer: <?= htmlspecialchars($row['owner_name']) ?>)</small>
                        <?php
                        // Show main image if exists
                        $imagePath = "uploads/parkings/{$row['id']}/1.jpg";
                        if (file_exists($imagePath)):
                            ?>
                            <div class="mt-1">
                                <img src="<?= $imagePath ?>" alt="Bild" style="height:60px; object-fit:cover; border-radius:4px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="?action=approve&id=<?= $row['id'] ?>" class="btn btn-success btn-sm me-2">Freigeben</a>
                        <a href="?action=reject&id=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Ablehnen</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
