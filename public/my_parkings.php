<?php
include_once "includes/parking_utils.php";

$pageTitle = "Meine Parkplätze";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_parking_id'])) {
        update_parking_price_availability((int)$_POST['update_parking_id'], $userId, $_POST['price'] ?? 0, $_POST['available_from'] ?? '', $_POST['available_to'] ?? '');
        $_SESSION['update_success'] = "Parkplatz aktualisiert.";
    } elseif (isset($_POST['delete_parking_id'])) {
        $result = delete_parking_if_possible((int)$_POST['delete_parking_id'], $userId);
        $_SESSION[$result['success'] ? 'update_success' : 'delete_error'] = $result['message'];
    }
    header('Location: my_parkings.php');
    exit;
}

$parkings = get_user_parkings($userId);
$statusClass = ['approved' => 'text-success', 'pending' => 'text-warning', 'draft' => 'badge bg-secondary', 'rejected' => 'text-danger'];
?>

<!DOCTYPE html>
<html lang="de">
<body>

<div class="container mt-5">
    <h2>Meine Parkplätze</h2>

    <?php if (!empty($_SESSION['delete_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['delete_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['delete_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['update_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['update_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['update_success']); ?>
    <?php endif; ?>

    <div class="row mt-4">
        <?php foreach ($parkings as $row):
            $images = get_image_files($row['id']);
            $mainImage = $images[0] ?? null;
        ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <?php if ($mainImage): ?>
                        <img src="<?= htmlspecialchars($mainImage) ?>" class="card-img-top" alt="Parkplatz">
                    <?php else: ?>
                        <div class="card-img-top d-flex justify-content-center align-items-center bg-light text-muted">Kein Bild</div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 80, "...")) ?></p>
                        <p>
                            <strong>Distrikt:</strong> <?= htmlspecialchars(get_district_name((int)$row['district_id']) ?: '—') ?><br>
                            <strong>Stadtteil:</strong> <?= htmlspecialchars(get_neighborhood_name((int)$row['neighborhood_id']) ?: '—') ?>
                        </p>
                        <p><strong>Preis:</strong> €<?= number_format($row['price'], 2) ?></p>
                        <p><strong>Status:</strong> <span class="<?= $statusClass[$row['status']] ?? 'text-secondary' ?>"><?= ucfirst($row['status']) ?></span></p>
                        <a href="parking.php?id=<?= (int)$row['id'] ?>" class="btn btn-primary btn-sm">Anschauen</a>
                        <?php if ($row['status'] === 'draft'): ?>
                            <a href="parking_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-info btn-sm">Bearbeiten</a>
                        <?php elseif ($row['status'] === 'approved'): ?>
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$row['id'] ?>">Bearbeiten</button>
                        <?php endif; ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="delete_parking_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Sicher?');">Löschen</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($row['status'] === 'approved'): ?>
                <div class="modal fade" id="editModal<?= (int)$row['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Bearbeiten</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="update_parking_id" value="<?= (int)$row['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Preis (€/Tag)</label>
                                        <input type="number" step="0.01" name="price" class="form-control" value="<?= number_format($row['price'], 2) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Von</label>
                                        <input type="date" name="available_from" class="form-control" value="<?= htmlspecialchars($row['available_from'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Bis</label>
                                        <input type="date" name="available_to" class="form-control" value="<?= htmlspecialchars($row['available_to'] ?? '') ?>">
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
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
