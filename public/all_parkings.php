<?php
include_once "includes/parking_utils.php";

$pageTitle = "Alle Parkplätze";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Admin status is stored in session by header.php
if (!($_SESSION['is_admin'] ?? false)) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    delete_parking($_POST['delete_id']);
    header("Location: all_parkings.php");
    exit;
}

// Filters
$filters = [];
$params = [];
$types = '';

// Status filter
if (!empty($_GET['status'])) {
    $filters[] = "p.status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

// Owner filter
if (!empty($_GET['owner'])) {
    $filters[] = "u.username LIKE ?";
    $params[] = "%" . $_GET['owner'] . "%";
    $types .= 's';
}

$whereSQL = '';
if ($filters) {
    $whereSQL = 'WHERE ' . implode(' AND ', $filters);
}

// Fetch parkings
$sql = "SELECT p.*, u.username AS owner_name FROM parkings p 
        LEFT JOIN users u ON p.owner_id = u.id
        $whereSQL
        ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
if ($types && !empty($params)) {
    if (count($params) === 1) {
        $stmt->bind_param($types, $params[0]);
    } elseif (count($params) === 2) {
        $stmt->bind_param($types, $params[0], $params[1]);
    }
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="de">
<body>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Alle Parkplätze</h1>

    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4 justify-content-center">
        <!-- Distrikt / Stadtteil filters could be added here later -->

        <!-- Status -->
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">Alle Status</option>
                <option value="approved" <?= (($_GET['status'] ?? '') === 'approved') ? 'selected' : '' ?>>Freigegeben</option>
                <option value="pending" <?= (($_GET['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Ausstehend</option>
                <option value="rejected" <?= (($_GET['status'] ?? '') === 'rejected') ? 'selected' : '' ?>>Abgelehnt</option>
            </select>
        </div>

        <!-- Owner -->
        <div class="col-md-2">
            <input type="text" name="owner" class="form-control" placeholder="Besitzer filtern" value="<?= htmlspecialchars($_GET['owner'] ?? '') ?>">
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
        </div>
    </form>

    <!-- Table -->
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
        <tr>
            <th>Titel</th>
            <th>Distrikt</th>
            <th>Stadtteil</th>
            <th>Preis</th>
            <th>Besitzer</th>
            <th>Erstellt am</th>
            <th>Geändert am</th>
            <th>Status</th>
            <th>Aktionen</th>
        </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td>
                    <a href="parking.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($row['title']) ?></a>
                </td>
                <td><?= htmlspecialchars(get_district_name($row['district_id']) ?: '—') ?></td>
                <td><?= htmlspecialchars(get_neighborhood_name($row['neighborhood_id']) ?: '—') ?></td>
                <td>€<?= number_format($row['price'],2) ?></td>
                <td><?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['modified_at']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td class="text-center">
                    <a href="parking.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info mb-1">Ansehen</a>

                    <form method="POST" style="display:inline;" onsubmit="return confirm('Möchten Sie diesen Parkplatz wirklich löschen?');">
                        <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <button type="submit" class="btn btn-sm btn-danger mb-1">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
