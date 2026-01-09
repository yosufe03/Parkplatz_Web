<?php
session_start();
include_once "includes/parking_utils.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// Handle POST requests (update or delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_parking_id'])) {
        // Update price and availability
        $updateId = (int)$_POST['update_parking_id'];
        $newPrice = $_POST['price'] ?? '';
        $availFrom = $_POST['available_from'] ?? '';
        $availTo = $_POST['available_to'] ?? '';

        $verify = $conn->prepare("SELECT status FROM parkings WHERE id = ? AND owner_id = ?");
        $verify->bind_param('ii', $updateId, $userId);
        $verify->execute();
        $verifyResult = $verify->get_result()->fetch_assoc();
        $verify->close();

        if ($verifyResult && $verifyResult['status'] === 'approved') {
            if (is_numeric($newPrice) && (float)$newPrice >= 0) {
                $priceFloat = (float)$newPrice;
                $stmt = $conn->prepare("UPDATE parkings SET price = ? WHERE id = ?");
                $stmt->bind_param('di', $priceFloat, $updateId);
                $stmt->execute();
                $stmt->close();
            }

            if ($availFrom && $availTo && isValidDate($availFrom) && isValidDate($availTo)) {
                $conn->query("DELETE FROM parking_availability WHERE parking_id = $updateId");
                $stmt = $conn->prepare("INSERT INTO parking_availability (parking_id, available_from, available_to) VALUES (?, ?, ?)");
                $stmt->bind_param('iss', $updateId, $availFrom, $availTo);
                $stmt->execute();
                $stmt->close();
            }

            $_SESSION['update_success'] = "Parkplatz aktualisiert.";
        }
    } elseif (isset($_POST['delete_parking_id'])) {
        // Delete parking
        $delId = (int)$_POST['delete_parking_id'];

        $verify = $conn->prepare("SELECT id FROM parkings WHERE id = ? AND owner_id = ?");
        $verify->bind_param('ii', $delId, $userId);
        $verify->execute();

        if ($verify->get_result()->num_rows > 0) {
            $bookingCheck = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE parking_id = ? AND booking_end >= ?");
            $bookingCheck->bind_param('is', $delId, $today);
            $bookingCheck->execute();

            if ($bookingCheck->get_result()->fetch_assoc()['count'] > 0) {
                $_SESSION['delete_error'] = "Diesen Parkplatz können Sie nicht löschen, da aktive Buchungen vorhanden sind.";
            } else {
                $conn->query("DELETE FROM bookings WHERE parking_id = $delId");
                $conn->query("DELETE FROM parking_availability WHERE parking_id = $delId");
                $conn->query("DELETE FROM parkings WHERE id = $delId");
                delete_dir_contents($delId);
            }
            $bookingCheck->close();
        }
        $verify->close();
    }

    header('Location: my_parkings.php');
    exit;
}

// Fetch all parkings
$stmt = $conn->prepare("SELECT * FROM parkings WHERE owner_id=? ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="de">
<?php
$pageTitle = "Meine Parkplätze";
include "includes/header.php";
?>

<style>
    .card-img-top {
        width: 100%;
        height: 180px;
        object-fit: cover;
    }
</style>
<body>

<div class="container mt-5">
    <h2>Meine Parkplätze</h2>

    <?php if (!empty($_SESSION['delete_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['delete_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['delete_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['update_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['update_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['update_success']); ?>
    <?php endif; ?>

    <div class="row mt-4">
        <?php while($row = $result->fetch_assoc()):
            $images = get_image_files($row['id']);
            $mainImage = $images[0] ?? null;
            $districtName = get_district_name((int)$row['district_id']);
            $neighborhoodName = get_neighborhood_name((int)$row['neighborhood_id']);

            // Fetch availability for this parking
            $availStmt = $conn->prepare("SELECT available_from, available_to FROM parking_availability WHERE parking_id = ? LIMIT 1");
            $availStmt->bind_param('i', $row['id']);
            $availStmt->execute();
            $availResult = $availStmt->get_result()->fetch_assoc();
            $availStmt->close();
            $availFrom = $availResult['available_from'] ?? '';
            $availTo = $availResult['available_to'] ?? '';
            ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <?php if ($mainImage): ?>
                        <img src="<?= htmlspecialchars($mainImage) ?>" class="card-img-top" alt="Parking Image">
                    <?php else: ?>
                        <div class="card-img-top d-flex justify-content-center align-items-center bg-light text-muted">
                            No Image
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 80, "...")) ?></p>
                        <p>
                            <strong>Distrikt:</strong> <?= htmlspecialchars($districtName ?: '—') ?><br>
                            <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName ?: '—') ?>
                        </p>
                        <p><strong>Preis:</strong> €<?= number_format($row['price'], 2) ?></p>
                        <p><strong>Status:</strong>
                            <?php
                            $statusMap = [
                                'approved' => ['class' => 'text-success', 'text' => 'Approved'],
                                'pending' => ['class' => 'text-warning', 'text' => 'Pending'],
                                'rejected' => ['class' => 'text-danger', 'text' => 'Rejected'],
                                'draft' => ['class' => 'badge bg-secondary', 'text' => 'Draft']
                            ];
                            $status = $statusMap[$row['status']] ?? ['class' => '', 'text' => htmlspecialchars($row['status'])];
                            echo "<span class=\"{$status['class']}\">{$status['text']}</span>";
                            ?>
                        </p>
                        <a href="parking.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">View</a>
                        <?php if ($row['status'] === 'draft'): ?>
                            <a href="parking_edit.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">Edit</a>
                        <?php elseif ($row['status'] === 'approved'): ?>
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
                        <?php endif; ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="delete_parking_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Sicher?');">Delete</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <?php if ($row['status'] === 'approved'): ?>
                <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Bearbeiten</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="update_parking_id" value="<?= $row['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Preis (€/Tag)</label>
                                        <input type="number" step="0.01" name="price" class="form-control" value="<?= number_format($row['price'], 2) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Von</label>
                                        <input type="date" name="available_from" class="form-control" value="<?= htmlspecialchars($availFrom) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Bis</label>
                                        <input type="date" name="available_to" class="form-control" value="<?= htmlspecialchars($availTo) ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                    <button type="submit" class="btn btn-primary">Speichern</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
