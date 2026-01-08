<?php
session_start();
include('includes/db_connect.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Check admin
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
if (!$row || $row['role'] !== 'admin') {
    die('Access denied');
}
$stmt->close();

// Handle POST actions: add_district, delete_district, add_neighborhood, delete_neighborhood
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_district'])) {
        $name = trim($_POST['district_name'] ?? '');
        if ($name !== '') {
            $i = $conn->prepare("INSERT INTO districts (name) VALUES (?)");
            $i->bind_param('s', $name);
            $i->execute();
            $i->close();
        }
    }
    if (isset($_POST['delete_district'])) {
        $did = (int)$_POST['district_id'];
        $d = $conn->prepare("DELETE FROM districts WHERE id = ?");
        $d->bind_param('i', $did);
        $d->execute();
        $d->close();
    }

    if (isset($_POST['add_neighborhood'])) {
        $nid = (int)$_POST['district_for_neigh'];
        $nname = trim($_POST['neighborhood_name'] ?? '');
        if ($nname !== '' && $nid > 0) {
            $in = $conn->prepare("INSERT INTO neighborhoods (district_id, name) VALUES (?, ?)");
            $in->bind_param('is', $nid, $nname);
            $in->execute();
            $in->close();
        }
    }
    if (isset($_POST['delete_neighborhood'])) {
        $nn = (int)$_POST['neighborhood_id'];
        $dn = $conn->prepare("DELETE FROM neighborhoods WHERE id = ?");
        $dn->bind_param('i', $nn);
        $dn->execute();
        $dn->close();
    }

    header('Location: areas_manage.php');
    exit;
}

// Load districts and neighborhoods
$dstmt = $conn->prepare("SELECT * FROM districts ORDER BY name ASC");
$dstmt->execute();
$dres = $dstmt->get_result();
$districts = [];
while ($d = $dres->fetch_assoc()) $districts[] = $d;
$dstmt->close();

// Load neighborhoods grouped by district
$nstmt = $conn->prepare("SELECT * FROM neighborhoods ORDER BY name ASC");
$nstmt->execute();
$nres = $nstmt->get_result();
$neigh = [];
while ($n = $nres->fetch_assoc()) {
    $neigh[(int)$n['district_id']][] = $n;
}
$nstmt->close();

?>
<!DOCTYPE html>
<html lang="de">
<?php $pageTitle = 'Gebietsverwaltung'; include('includes/header.php'); ?>
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
            <ul class="list-group">
                <?php foreach ($districts as $d): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($d['name']) ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="district_id" value="<?= (int)$d['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="delete_district" onclick="return confirm('Delete district?');">Löschen</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="col-md-7">
            <h5>Stadtteile / Nachbarschaften</h5>
            <form method="POST" class="mb-3">
                <div class="row g-2">
                    <div class="col-md-6">
                        <select name="district_for_neigh" class="form-select">
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="neighborhood_name" class="form-control" placeholder="Neuer Stadtteil">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" name="add_neighborhood">Hinzufügen</button>
                    </div>
                </div>
            </form>

            <?php foreach ($districts as $d): ?>
                <h6 class="mt-3"><?= htmlspecialchars($d['name']) ?></h6>
                <ul class="list-group mb-2">
                    <?php $list = $neigh[(int)$d['id']] ?? []; ?>
                    <?php if (empty($list)): ?>
                        <li class="list-group-item text-muted">Keine Stadtteile</li>
                    <?php else: ?>
                        <?php foreach ($list as $n): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($n['name']) ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="neighborhood_id" value="<?= (int)$n['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="delete_neighborhood" onclick="return confirm('Delete neighborhood?');">Löschen</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
