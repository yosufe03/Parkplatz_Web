<?php
include_once "includes/parking_utils.php";

$pageTitle = "Ausstehende Parkplätze";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!($_SESSION['is_admin'] ?? false)) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['action'], $_GET['id'])) {
    $status = $_GET['action'] === 'approve' ? 'approved' : 'rejected';
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE parkings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: pending_parkings.php");
    exit;
}

$sql = "SELECT p.*, u.username AS owner_name, d.name AS district_name, n.name AS neighborhood_name 
        FROM parkings p 
        LEFT JOIN users u ON p.owner_id = u.id 
        LEFT JOIN districts d ON p.district_id = d.id 
        LEFT JOIN neighborhoods n ON p.neighborhood_id = n.id 
        WHERE p.status = 'pending' 
        ORDER BY p.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="de">
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
                    <?php while ($row = $result->fetch_assoc()):
                        $imagePath = null;
                        foreach (['jpg', 'jpeg', 'png'] as $ext) {
                            $path = __DIR__ . "/uploads/parkings/{$row['id']}/1.$ext";
                            if (file_exists($path)) {
                                $imagePath = "uploads/parkings/{$row['id']}/1.$ext";
                                break;
                            }
                        }
                    ?>
                        <tr>
                            <td><a href="parking.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
                            <td><?= htmlspecialchars($row['district_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($row['neighborhood_name'] ?? '—') ?></td>
                            <td>€<?= number_format((float)$row['price'], 2) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                            <td><?= htmlspecialchars($row['owner_name']) ?></td>
                            <td><?php if ($imagePath): ?><img src="<?= htmlspecialchars($imagePath) ?>" class="parking-thumbnail"><?php else: ?>—<?php endif; ?></td>
                            <td class="text-center">
                                <a href="?action=approve&id=<?= (int)$row['id'] ?>" class="btn btn-success btn-sm">Freigeben</a>
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
