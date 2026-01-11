<?php
include_once "includes/parking_utils.php";

$pageTitle = "Benutzer verwalten";
include "includes/header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!($_SESSION['is_admin'] ?? false)) {
    header("Location: index.php");
    exit;
}

// Get filter parameters
$filterStatus = $_GET['filter_status'] ?? '';
$filterRole = $_GET['filter_role'] ?? '';
$searchName = $_GET['search_name'] ?? '';

// Build query with filters
$query = "SELECT id, username, email, role, active FROM users WHERE role = 'user'";
$params = [];
$types = "";

if ($searchName !== '') {
    $query .= " AND username LIKE ?";
    $params[] = '%' . $searchName . '%';
    $types .= "s";
}

if ($filterStatus !== '') {
    $query .= " AND active = ?";
    $params[] = (int)$filterStatus;
    $types .= "i";
}

if ($filterRole !== '') {
    $query .= " AND role = ?";
    $params[] = $filterRole;
    $types .= "s";
}

$query .= " ORDER BY id DESC";

if (count($params) > 0) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($query);
}

// Handle actions
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'sperren') {
        $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE parkings SET status = 'rejected' WHERE owner_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'aktivieren') {
        $stmt = $conn->prepare("UPDATE users SET active = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'loeschen') {
        delete_user($id);
    }

    // Redirect back with filters preserved
    $redirectUrl = "user_manage.php";
    if ($searchName !== '' || $filterStatus !== '' || $filterRole !== '') {
        $redirectUrl .= "?";
        if ($searchName !== '') $redirectUrl .= "search_name=" . urlencode($searchName);
        if ($filterStatus !== '') $redirectUrl .= ($searchName !== '' ? "&" : "") . "filter_status=" . urlencode($filterStatus);
        if ($filterRole !== '') $redirectUrl .= (($searchName !== '' || $filterStatus !== '') ? "&" : "") . "filter_role=" . urlencode($filterRole);
    }
    header("Location: " . $redirectUrl);
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<body>
<div class="container-xxl mt-5">
    <h1 class="text-center">Benutzer moderieren</h1>
    <p class="text-center text-muted mb-4">Verwalte Benutzerkonten der Plattform.</p>

    <!-- Filter Form -->
    <form method="GET" class="row g-3 justify-content-center mb-4">
        <div class="col-12 col-md-4 col-lg-3">
            <input type="text" name="search_name" class="form-control"
                   placeholder="Nach Name suchen..."
                   value="<?= htmlspecialchars($searchName) ?>">
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <select name="filter_status" class="form-select">
                <option value="">Status - Alle</option>
                <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Aktiv</option>
                <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Gesperrt</option>
            </select>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <select name="filter_role" class="form-select">
                <option value="">Rolle - Alle</option>
                <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="user" <?= $filterRole === 'user' ? 'selected' : '' ?>>User</option>
            </select>
        </div>

        <div class="col-12 col-lg-2">
            <button type="submit" class="btn btn-primary w-100">Filtern</button>
        </div>
    </form>

    <!-- User Cards -->
    <div class="row g-3">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php $result->data_seek(0); ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-12 col-md-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-3"><?= htmlspecialchars($row['username']) ?></h5>

                            <p class="card-text mb-2">
                                <small><strong>Email:</strong><br><?= htmlspecialchars($row['email']) ?></small>
                            </p>

                            <p class="card-text mb-2">
                                <small><strong>Rolle:</strong> <?= htmlspecialchars($row['role']) ?></small>
                            </p>

                            <p class="card-text mb-3">
                                <small><strong>Status:</strong>
                                    <?= $row['active']
                                            ? '<span class="badge bg-success">Aktiv</span>'
                                            : '<span class="badge bg-danger">Gesperrt</span>' ?>
                                </small>
                            </p>

                            <form method="GET" class="d-grid gap-2 mt-auto">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                                <!-- preserve filters -->
                                <input type="hidden" name="search_name" value="<?= htmlspecialchars($searchName) ?>">
                                <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filterStatus) ?>">
                                <input type="hidden" name="filter_role" value="<?= htmlspecialchars($filterRole) ?>">

                                <button type="submit"
                                        class="btn btn-sm w-100 <?= $row['active'] ? 'btn-warning' : 'btn-success' ?>"
                                        name="action"
                                        value="<?= $row['active'] ? 'sperren' : 'aktivieren' ?>">
                                    <?= $row['active'] ? 'Sperren' : 'Aktivieren' ?>
                                </button>

                                <button type="submit"
                                        class="btn btn-sm btn-danger w-100"
                                        name="action"
                                        value="loeschen"
                                        onclick="return confirm('Benutzer wirklich löschen?');">
                                    Löschen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center mb-0">Keine Benutzer gefunden.</div>
            </div>
        <?php endif; ?>
    </div>
    <!-- User Cards -->
</div>

</body>
</html>
