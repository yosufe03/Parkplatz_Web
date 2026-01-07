<?php
session_start();
include("includes/db_connect.php");

// Zugriff prüfen
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Prüfen ob Admin
$userId = $_SESSION['user_id'];
$stmtUser = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$currentUser = $resultUser->fetch_assoc();

if ($currentUser['role'] !== 'admin') {
    die("Zugriff verweigert!");
}

// Benutzeraktionen
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === "sperren") {
        $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    if ($action === "aktivieren") {
        $stmt = $conn->prepare("UPDATE users SET active = 1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    if ($action === "loeschen") {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: user_manage.php");
    exit;
}

// Userliste abrufen
$result = $conn->query("SELECT id, username, email, role, active FROM users ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="de">
<?php
$pageTitle = "Benutzerverwaltung";
include("includes/header.php");
?>

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
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td>
                    <?= $row['active'] ? "<span class='text-success'>Aktiv</span>" : "<span class='text-danger'>Gesperrt</span>" ?>
                </td>
                <td>
                    <?php if ($row['active']): ?>
                        <a href="user_manage.php?action=sperren&id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Sperren</a>
                    <?php else: ?>
                        <a href="user_manage.php?action=aktivieren&id=<?= $row['id'] ?>" class="btn btn-success btn-sm">Aktivieren</a>
                    <?php endif; ?>

                    <a href="user_manage.php?action=loeschen&id=<?= $row['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Diesen Benutzer wirklich löschen?')">
                        Löschen
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
