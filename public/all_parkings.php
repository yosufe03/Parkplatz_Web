<?php
include("includes/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if admin
$stmtUser = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$currentUser = $resultUser->fetch_assoc();
$isAdmin = $currentUser['role'] === 'admin';

if (!$isAdmin) {
    die("Access denied.");
}

// Sorting & filtering
$allowedSort = ['title','location','price','created_at','modified_at'];
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
$sort = in_array($sort,$allowedSort) ? $sort : 'id';

// Toggle order function
function toggleOrder($currentSort,$currentOrder,$column){
    if($currentSort === $column){
        return $currentOrder === 'ASC' ? 'DESC' : 'ASC';
    }
    return 'ASC';
}

// Filters
$filters = [];
$params = [];
$types = '';

// Location filter
if (!empty($_GET['location'])) {
    $filters[] = "p.location LIKE ?";
    $params[] = "%" . $_GET['location'] . "%";
    $types .= 's';
}

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
        ORDER BY $sort $order";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Alle Parkplätze - ParkShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include("includes/header.php"); ?>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Alle Parkplätze</h1>

    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4 justify-content-center">
        <!-- Location -->
        <div class="col-md-3">
            <input type="text" name="location" class="form-control" placeholder="Ort filtern" value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
        </div>

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
        <thead class="table-dark text-center">
        <tr>
            <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'title','order'=>toggleOrder($sort,$order,'title')])) ?>" class="text-white text-decoration-none">Titel</a></th>
            <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'location','order'=>toggleOrder($sort,$order,'location')])) ?>" class="text-white text-decoration-none">Ort</a></th>
            <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'price','order'=>toggleOrder($sort,$order,'price')])) ?>" class="text-white text-decoration-none">Preis</a></th>
            <th>Besitzer</th>
            <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'created_at','order'=>toggleOrder($sort,$order,'created_at')])) ?>" class="text-white text-decoration-none">Erstellt am</a></th>
            <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'modified_at','order'=>toggleOrder($sort,$order,'modified_at')])) ?>" class="text-white text-decoration-none">Geändert am</a></th>
            <th>Status</th>
            <th>Aktionen</th>
        </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td>€<?= number_format($row['price'],2) ?></td>
                <td><?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['modified_at']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td>
                    <a href="parking_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning mb-1">Bearbeiten</a>
                    <a href="parking_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Möchten Sie diesen Parkplatz wirklich löschen?')">Löschen</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
