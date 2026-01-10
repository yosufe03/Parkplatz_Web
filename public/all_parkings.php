<?php
include_once "includes/parking_utils.php";

// Include header FIRST to start session
$pageTitle = "Alle Parkplätze";
include "includes/header.php";

// NOW check auth - session is started
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$_SESSION['return_to'] = $_SERVER['REQUEST_URI']; // store current page

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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];

    if ($isAdmin) {
        $stmtDel = $conn->prepare("DELETE FROM parkings WHERE id=?");
        $stmtDel->bind_param("i", $deleteId);
        $stmtDel->execute();
        $stmtDel->close();
        header("Location: all_parkings.php"); // reload page
        exit;
    }
}

// Sorting & filtering
$allowedSort = ['title','price','created_at','modified_at'];
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

// (location removed) -- filters now include status and owner only

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
<?php
    $pageTitle = "Alle Parkplätze";
    include("includes/header.php");
?>
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
        <thead class="table-dark text-center">
        <tr>
            <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'title','order'=>toggleOrder($sort,$order,'title')])) ?>" class="text-white text-decoration-none">Titel</a></th>
            <th>Distrikt</th>
            <th>Stadtteil</th>
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
                <td>
                    <a href="parking.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($row['title']) ?></a>
                </td>
                <?php
                    $districtName = '';
                    $neighborhoodName = '';
                    if (!empty($row['district_id'])) {
                        $dq = $conn->prepare("SELECT name FROM districts WHERE id = ? LIMIT 1");
                        $did = (int)$row['district_id'];
                        $dq->bind_param('i', $did);
                        $dq->execute();
                        $dres = $dq->get_result();
                        if ($dr = $dres->fetch_assoc()) $districtName = $dr['name'];
                        $dq->close();
                    }
                    if (!empty($row['neighborhood_id'])) {
                        $nq = $conn->prepare("SELECT name FROM neighborhoods WHERE id = ? LIMIT 1");
                        $nid = (int)$row['neighborhood_id'];
                        $nq->bind_param('i', $nid);
                        $nq->execute();
                        $nres = $nq->get_result();
                        if ($nr = $nres->fetch_assoc()) $neighborhoodName = $nr['name'];
                        $nq->close();
                    }
                ?>
                <td><?= htmlspecialchars($districtName ?: '—') ?></td>
                <td><?= htmlspecialchars($neighborhoodName ?: '—') ?></td>
                <td>€<?= number_format($row['price'],2) ?></td>
                <td><?= htmlspecialchars($row['owner_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['modified_at']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td class="text-center">
                    <a href="parking.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info mb-1">Ansehen</a>

                    <form method="POST" style="display:inline;" onsubmit="return confirm('Möchten Sie diesen Parkplatz wirklich löschen?');">
                        <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
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
