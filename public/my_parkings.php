<?php
session_start();
include("includes/db_connect.php");
include_once __DIR__ . '/includes/parking_utils.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Store this page as the return URL for edits/views
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

// Fetch all parkings for this user
$stmt = $conn->prepare("SELECT * FROM parkings WHERE owner_id=? ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
// Fetch current user role so we can conditionally show edit links for drafts
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$roleStmt->bind_param('i', $userId);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$currentUser = $roleRes->fetch_assoc();
$roleStmt->close();
$isAdmin = ($currentUser && $currentUser['role'] === 'admin');

// Handle publish and delete requests from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Publish a draft (owner or admin)
    if (isset($_POST['publish_parking_id'])) {
        $pubId = (int)$_POST['publish_parking_id'];
        if ($pubId > 0) {
            // verify ownership or admin
            $chk = $conn->prepare("SELECT owner_id FROM parkings WHERE id = ? LIMIT 1");
            $chk->bind_param('i', $pubId);
            $chk->execute();
            $cres = $chk->get_result();
            if ($crow = $cres->fetch_assoc()) {
                $isOwner = ((int)$crow['owner_id'] === (int)$userId);
                if ($isOwner || $isAdmin) {
                    $u = $conn->prepare("UPDATE parkings SET status = 'pending' WHERE id = ?");
                    $u->bind_param('i', $pubId);
                    $u->execute();
                    $u->close();
                    // if this was the draft tracked in session, clear it
                    if (isset($_SESSION['draft_parking_id']) && $_SESSION['draft_parking_id'] == $pubId) unset($_SESSION['draft_parking_id']);
                }
            }
            $chk->close();
        }
        header('Location: my_parkings.php');
        exit;
    }

    if (isset($_POST['delete_parking_id'])) {
        $delId = (int)$_POST['delete_parking_id'];
    if ($delId > 0) {
        // verify ownership (admins may delete elsewhere)
        $chk = $conn->prepare("SELECT owner_id FROM parkings WHERE id = ? LIMIT 1");
        $chk->bind_param('i', $delId);
        $chk->execute();
        $cres = $chk->get_result();
        $ownerMatch = false;
        if ($crow = $cres->fetch_assoc()) {
            if ((int)$crow['owner_id'] === (int)$userId) $ownerMatch = true;
        }
        $chk->close();

        if ($ownerMatch) {
            // delete related bookings
            $stmtB = $conn->prepare("DELETE FROM bookings WHERE parking_id = ?");
            $stmtB->bind_param('i', $delId);
            $stmtB->execute();
            $stmtB->close();

            // delete availability
            $stmtA = $conn->prepare("DELETE FROM parking_availability WHERE parking_id = ?");
            $stmtA->bind_param('i', $delId);
            $stmtA->execute();
            $stmtA->close();

            // remove files and folder
            delete_dir_contents($delId);

            // delete parking row
            $stmtD = $conn->prepare("DELETE FROM parkings WHERE id = ? AND owner_id = ?");
            $stmtD->bind_param('ii', $delId, $userId);
            $stmtD->execute();
            $stmtD->close();
        }
    }

        // redirect to avoid reposts and refresh list
        header('Location: my_parkings.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<?php
    $pageTitle = "Meine Parkplätze";
    include("includes/header.php");
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
    <div class="row mt-4">
        <?php while($row = $result->fetch_assoc()):
            $images = get_image_files($row['id']);
            $mainImage = $images[0] ?? null;
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
                        <p><strong>Distrikt:</strong> <?= htmlspecialchars($districtName ?: '—') ?> <br>
                        <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName ?: '—') ?></p>
                        <p><strong>Price:</strong> €<?= number_format($row['price'], 2) ?></p>
                        <p><strong>Status:</strong>
                            <?php
                            switch ($row['status']) {
                                case 'approved': echo '<span class="text-success">Approved</span>'; break;
                                case 'pending': echo '<span class="text-warning">Pending</span>'; break;
                                case 'rejected': echo '<span class="text-danger">Rejected</span>'; break;
                                case 'draft': echo '<span class="badge bg-secondary">Draft</span>'; break;
                                default: echo htmlspecialchars($row['status']);
                            }
                            ?>
                        </p>
                        <a href="parking.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">View</a>
                        <?php if ($row['status'] === 'draft' || $isAdmin): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="publish_parking_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Publish</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="delete_parking_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
