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

$stmt = $conn->prepare("SELECT p.*, u.username AS owner_name,
           d.name AS district_name,
           n.name AS neighborhood_name
    FROM parkings p
    LEFT JOIN users u ON p.owner_id = u.id
    LEFT JOIN districts d ON p.district_id = d.id
    LEFT JOIN neighborhoods n ON p.neighborhood_id = n.id
    WHERE p.status='pending'
    ORDER BY p.created_at ASC");
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
        <div class="table-responsive mt-3">
            <table class="table table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Titel</th>
                        <th>Distrikt</th>
                        <th>Stadtteil</th>
                        <th>Preis</th>
                        <th>Erstellt am</th>
                        <th>Besitzer</th>
                        <th>Bild</th>
                        <th class="text-center">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $neigh = $row['neighborhood_name'] ?? '';
                            $dist = $row['district_name'] ?? '';
                            $imagePath = null;
                            // Try common image extensions for the main image (1.jpg, 1.jpeg, 1.png)
                            $possibleExt = ['jpg','jpeg','png'];
                            foreach ($possibleExt as $ext) {
                                $candidateWeb = "uploads/parkings/{$row['id']}/1." . $ext;
                                $candidateFs = __DIR__ . '/' . $candidateWeb;
                                if (file_exists($candidateFs)) {
                                    $imagePath = $candidateWeb;
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <a href="parking.php?id=<?= (int)$row['id'] ?>" class="text-decoration-underline link-primary" title="Zum Eintrag"><?= htmlspecialchars($row['title'] ?? '') ?></a>
                            </td>
                            <td><?= $dist !== '' ? htmlspecialchars($dist) : '—' ?></td>
                            <td><?= $neigh !== '' ? htmlspecialchars($neigh) : '—' ?></td>
                            <td>€<?= number_format((float)($row['price'] ?? 0), 2) ?></td>
                            <td><?= !empty($row['created_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))) : '—' ?></td>
                            <td><?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($imagePath !== null): ?>
                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="Bild" style="height:60px; object-fit:cover; border-radius:4px;">
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="?action=approve&id=<?= (int)$row['id'] ?>" class="btn btn-success btn-sm me-2">Freigeben</a>
                                <a href="?action=reject&id=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm">Ablehnen</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
