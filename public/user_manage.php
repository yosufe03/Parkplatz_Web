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
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: user_manage.php");
    exit;
}

$result = $conn->query("SELECT id, username, email, role, active FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="de">
<body>
<div class="container mt-5">
    <h1 class="text-center">Benutzer moderieren</h1>
    <p class="text-center text-muted mb-4">Verwalte Benutzerkonten der Plattform.</p>

    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Email</th>
                <th>Rolle</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td>
                            <?= $row['active'] ? '<span class="text-success">Aktiv</span>' : '<span class="text-danger">Gesperrt</span>' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <form method="GET">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $row['active'] ? 'btn-warning' : 'btn-success' ?>" name="action" value="<?= $row['active'] ? 'sperren' : 'aktivieren' ?>">
                                        <?= $row['active'] ? 'Sperren' : 'Aktivieren' ?>
                                    </button>
                                </form>
                                <form method="GET">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="action" value="loeschen">
                                    <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">Keine Benutzer gefunden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
