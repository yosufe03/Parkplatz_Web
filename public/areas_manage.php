<?php
include_once "includes/parking_utils.php";

$pageTitle = "Bereiche verwalten";
include "includes/header.php";

if (!($_SESSION['is_admin'] ?? false)) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_district'])) {
        add_district($_POST['district_name'] ?? '');
    } elseif (isset($_POST['delete_district'])) {
        delete_district($_POST['district_id']);
    } elseif (isset($_POST['add_neighborhood'])) {
        add_neighborhood($_POST['district_for_neigh'], $_POST['neighborhood_name'] ?? '');
    } elseif (isset($_POST['delete_neighborhood'])) {
        delete_neighborhood($_POST['neighborhood_id']);
    }

    $redirect = 'areas_manage.php';
    $district_id = (int)($_POST['district_for_neigh'] ?? ($_GET['district_id'] ?? 0));
    if ($district_id > 0) {
        $redirect .= '?district_id=' . $district_id;
    }
    header('Location: ' . $redirect);
    exit;
}

$districts = get_districts();
$selected_district_id = (int)($_POST['district_for_neigh'] ?? ($_GET['district_id'] ?? 0));
$selected_neighborhoods = $selected_district_id > 0 ? get_neighborhoods_for_district($selected_district_id) : [];
?>
<!DOCTYPE html>
<html lang="de">
<body>
<div class="container mt-4">
    <h2>Gebiete verwalten</h2>

    <div class="row mt-3">
        <div class="col-md-5">
            <h5>Distrikte</h5>
            <form method="POST" class="mb-3">
                <div class="input-group">
                    <input type="text" name="district_name" class="form-control" placeholder="Neuer Distrikt">
                    <button class="btn btn-primary" name="add_district">Hinzufügen</button>
                </div>
            </form>
            <table class="table table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th style="width: 120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($districts as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['name']) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="district_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" name="delete_district">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="col-md-7">
            <h5>Stadtteile</h5>
            <form method="GET" class="mb-3">
                <div class="input-group mb-2">
                    <select name="district_id" class="form-select">
                        <option value="0">Distrikt wählen...</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $selected_district_id === (int)$d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-secondary">Wählen</button>
                </div>
            </form>

            <?php if ($selected_district_id > 0): ?>
                <form method="POST" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="district_for_neigh" value="<?= $selected_district_id ?>">
                        <input type="text" name="neighborhood_name" class="form-control" placeholder="Neuer Stadtteil">
                        <button type="submit" class="btn btn-primary" name="add_neighborhood">Hinzufügen</button>
                    </div>
                </form>

                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($selected_neighborhoods)): ?>
                            <tr><td colspan="2" class="text-muted">Keine Stadtteile</td></tr>
                        <?php else: ?>
                            <?php foreach ($selected_neighborhoods as $n): ?>
                                <tr>
                                    <td><?= htmlspecialchars($n['name']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="neighborhood_id" value="<?= (int)$n['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" name="delete_neighborhood">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">Bitte wählen Sie einen Distrikt</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
