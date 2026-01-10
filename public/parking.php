<?php
include("includes/parking_utils.php");
// start session early so we can read `$_SESSION['user_id']` before header include
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = isset($_GET['id']) ? (int)trim($_GET['id']) : 0;
if ($id <= 0) die("Invalid parking id.");

function normalizeMonthYear($m, $y) {
    if ($m < 1) { $m = 12; $y--; }
    if ($m > 12) { $m = 1; $y++; }
    return [$m, $y];
}

$today = date('Y-m-d');

/* ---------- Selected range from URL (from search or calendar clicks) ---------- */
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

if ($start_date && !isValidDate($start_date)) $start_date = null;
if ($end_date && !isValidDate($end_date)) $end_date = null;

if ($start_date && $start_date < $today) { $start_date = null; $end_date = null; }
if ($end_date && $end_date < $today) $end_date = null;

if ($start_date && $end_date && $end_date < $start_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

/* ---------- Month/year logic ----------
   - If month/year provided (arrows or day clicks), use them.
   - Else (first load), if start_date exists, open that month.
   - Else open current month.
*/
if (isset($_GET['month'], $_GET['year'])) {
    $month = (int)$_GET['month'];
    $year  = (int)$_GET['year'];
} elseif ($start_date) {
    $dt = new DateTime($start_date);
    $month = (int)$dt->format('m');
    $year  = (int)$dt->format('Y');
} else {
    $month = (int)date('m');
    $year  = (int)date('Y');
}
[$month, $year] = normalizeMonthYear($month, $year);

/* ---------- Parking ---------- */
$stmt = $conn->prepare("
    SELECT p.*, u.username AS owner_name
    FROM parkings p
    LEFT JOIN users u ON p.owner_id = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Parking not found");
$parking = $res->fetch_assoc();
$stmt->close();

// Check if user is allowed to view this parking
$isOwner = isset($_SESSION['user_id']) && $parking['owner_id'] == $_SESSION['user_id'];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if ($parking['status'] === 'rejected' && !$isOwner && !$isAdmin) {
    die("Parkplatz nicht gefunden oder keine Berechtigung.");
}

/* ---------- Reviews (avg, list, user's review) ---------- */
$avgRating = 0.0;
$reviewCount = 0;
$reviews = [];

$revAvgStmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM parking_reviews WHERE parking_id = ?");
$revAvgStmt->bind_param("i", $id);
$revAvgStmt->execute();
$revAvgRes = $revAvgStmt->get_result();
if ($row = $revAvgRes->fetch_assoc()) {
    $avgRating = $row['avg_rating'] !== null ? (float)$row['avg_rating'] : 0.0;
    $reviewCount = (int)$row['review_count'];
}
$revAvgStmt->close();

$revStmt = $conn->prepare("SELECT r.*, u.username FROM parking_reviews r JOIN users u ON r.user_id = u.id WHERE r.parking_id = ? ORDER BY r.created_at DESC LIMIT 20");
$revStmt->bind_param("i", $id);
$revStmt->execute();
$revRes = $revStmt->get_result();
while ($r = $revRes->fetch_assoc()) {
    $reviews[] = $r;
}
$revStmt->close();

$userReview = null;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $ur = $conn->prepare("SELECT rating, comment FROM parking_reviews WHERE parking_id = ? AND user_id = ?");
    $ur->bind_param("ii", $id, $uid);
    $ur->execute();
    $urRes = $ur->get_result();
    if ($urRes && $urRes->num_rows > 0) $userReview = $urRes->fetch_assoc();
    $ur->close();

    // Favorite status
    $favStmt = $conn->prepare("SELECT id FROM favorites WHERE parking_id = ? AND user_id = ? LIMIT 1");
    $favStmt->bind_param("ii", $id, $uid);
    $favStmt->execute();
    $favRes = $favStmt->get_result();
    $isFavorite = ($favRes && $favRes->num_rows > 0);
    $favStmt->close();
}

/* ---------- Images ---------- */
$imageDir = "uploads/parkings/" . $parking['id'] . "/";
$images = glob($imageDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
sort($images);

/* ---------- Availability (DATE or DATETIME compatible) ---------- */
$availableDates = [];
$availStmt = $conn->prepare("
    SELECT available_from, available_to
    FROM parking_availability
    WHERE parking_id = ?
");
$availStmt->bind_param("i", $id);
$availStmt->execute();
$availRes = $availStmt->get_result();
while ($row = $availRes->fetch_assoc()) {
    $s = new DateTime(substr($row['available_from'], 0, 10));
    $e = new DateTime(substr($row['available_to'], 0, 10));
    while ($s <= $e) {
        $availableDates[$s->format('Y-m-d')] = true;
        $s->modify('+1 day');
    }
}
$availStmt->close();

/* ---------- Booked dates (disable in calendar) ---------- */
$bookedDates = [];
$bookStmt = $conn->prepare("
    SELECT booking_start, booking_end
    FROM bookings
    WHERE parking_id = ?
");
$bookStmt->bind_param("i", $id);
$bookStmt->execute();
$bookRes = $bookStmt->get_result();
while ($b = $bookRes->fetch_assoc()) {
    $s = new DateTime($b['booking_start']);
    $e = new DateTime($b['booking_end']);
    while ($s <= $e) {
        $bookedDates[$s->format('Y-m-d')] = true;
        $s->modify('+1 day');
    }
}
$bookStmt->close();

/* ---------- Optional: reject ranges that include booked/unavailable ---------- */
$range_error = '';
if ($start_date && $end_date) {
    $cur = new DateTime($start_date);
    $end = new DateTime($end_date);
    while ($cur <= $end) {
        $dk = $cur->format('Y-m-d');
        if (!isset($availableDates[$dk])) {
            $range_error = "Selected range contains unavailable dates.";
            $start_date = null; $end_date = null;
            break;
        }
        if (isset($bookedDates[$dk])) {
            $range_error = "Selected range contains already booked dates.";
            $start_date = null; $end_date = null;
            break;
        }
        $cur->modify('+1 day');
    }
}

/* ---------- Calendar math ---------- */
$firstDay = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N'); // 1=Mon..7=Sun

[$pm, $py] = normalizeMonthYear($month - 1, $year);
[$nm, $ny] = normalizeMonthYear($month + 1, $year);

/* keep range in links */
$keepRange = "";
if ($start_date) $keepRange .= "&start_date=" . urlencode($start_date);
if ($end_date)   $keepRange .= "&end_date=" . urlencode($end_date);
?>

<!DOCTYPE html>
<html lang="de">
<?php include("includes/header.php"); ?>
<style>
    .parking-img { width:100%; height:400px; object-fit:cover; border-radius:10px; }

    /* Calendar sizing */
    .cal td, .cal th { width:14.285%; vertical-align:top; padding:.35rem; }

    /* Day pill */
    .daybox{
        display:flex;
        align-items:center;
        justify-content:center;
        height:44px;
        border-radius:999px;
        text-decoration:none;
        font-weight:600;
        border:1px solid transparent;
        user-select:none;
    }

    /* States */
    .day-disabled { opacity:.45; cursor:not-allowed; background:#f8f9fa; }
    .day-available { background: rgba(25,135,84,.10); border-color: rgba(25,135,84,.25); }
    .day-booked { background: rgba(220,53,69,.12); border-color: rgba(220,53,69,.25); }

    /* Range visualization (better!) */
    .day-inrange{
        background: rgba(13,110,253,.12);
        border-color: rgba(13,110,253,.25);
        border-radius: 0; /* makes it look like a continuous bar */
    }
    .day-range-start{
        background:#0d6efd;
        color:#fff;
        border-color:#0d6efd;
        border-top-left-radius:999px;
        border-bottom-left-radius:999px;
        border-top-right-radius:0;
        border-bottom-right-radius:0;
    }
    .day-range-end{
        background:#0d6efd;
        color:#fff;
        border-color:#0d6efd;
        border-top-right-radius:999px;
        border-bottom-right-radius:999px;
        border-top-left-radius:0;
        border-bottom-left-radius:0;
    }
    /* Single-day range (start=end) */
    .day-range-single{
        background:#0d6efd;
        color:#fff;
        border-color:#0d6efd;
        border-radius:999px;
    }

    /* Hover */
    a.daybox:hover { filter: brightness(0.98); }

    /* Legend dots */
    .legend-dot{ display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; vertical-align:middle; }
</style>
<body>

<div class="container mt-4">
    <div class="row">

        <!-- LEFT -->
        <div class="col-md-7">
            <?php if ($images): ?>
                <div id="carousel" class="carousel slide mb-4">
                    <div class="carousel-inner">
                        <?php foreach ($images as $i => $img): ?>
                            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($img) ?>" class="parking-img" alt="Parking image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h5 class="mb-2">Availability</h5>

            <?php if ($range_error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($range_error) ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <a class="btn btn-outline-secondary btn-sm"
                   href="parking.php?id=<?= $id ?>&month=<?= $pm ?>&year=<?= $py ?><?= $keepRange ?>">←</a>

                <strong><?= htmlspecialchars($firstDay->format('F Y')) ?></strong>

                <a class="btn btn-outline-secondary btn-sm"
                   href="parking.php?id=<?= $id ?>&month=<?= $nm ?>&year=<?= $ny ?><?= $keepRange ?>">→</a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered cal bg-white">
                    <thead class="table-light">
                    <tr class="text-center">
                        <th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th>Sa</th><th>So</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <?php
                        for ($i = 1; $i < $startWeekday; $i++) echo "<td></td>";

                        $col = $startWeekday;
                        for ($day = 1; $day <= $daysInMonth; $day++, $col++) {
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

                            $isPast   = ($date < $today);
                            $isAvail  = isset($availableDates[$date]);
                            $isBooked = isset($bookedDates[$date]);

                            $inRange = ($start_date && $end_date && $date >= $start_date && $date <= $end_date);
                            $isStart = ($start_date && $date === $start_date);
                            $isEnd   = ($end_date && $date === $end_date);

                            $clickable = (!$isPast && $isAvail && !$isBooked);

                            // include month/year in day links so view stays where user is
                            if (!$start_date) {
                                $url = "parking.php?id=$id&month=$month&year=$year&start_date=$date";
                            } elseif (!$end_date) {
                                $url = "parking.php?id=$id&month=$month&year=$year&start_date=$start_date&end_date=$date";
                            } else {
                                $url = "parking.php?id=$id&month=$month&year=$year&start_date=$date";
                            }

                            // day class
                            $cls = "daybox";

                            if ($isPast || !$isAvail) {
                                $cls .= " day-disabled";
                            } elseif ($isBooked) {
                                $cls .= " day-booked";
                            } else {
                                $cls .= " day-available";
                            }

                            // range styling (overrides visuals)
                            if ($start_date && $end_date && $start_date === $end_date && $date === $start_date) {
                                $cls = "daybox day-range-single";
                            } else {
                                if ($inRange) $cls .= " day-inrange";
                                if ($isStart) $cls = "daybox day-range-start";
                                if ($isEnd)   $cls = "daybox day-range-end";
                            }

                            echo "<td class='text-center'>";
                            if ($clickable) {
                                echo "<a class='".htmlspecialchars($cls)."' href='".htmlspecialchars($url)."'>$day</a>";
                            } else {
                                echo "<span class='".htmlspecialchars($cls)."'>$day</span>";
                            }
                            echo "</td>";

                            if ($col % 7 === 0 && $day !== $daysInMonth) echo "</tr><tr>";
                        }

                        $endFill = (7 - (($col - 1) % 7)) % 7;
                        for ($i = 0; $i < $endFill; $i++) echo "<td></td>";
                        ?>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
                <span><span class="legend-dot" style="background: rgba(25,135,84,.35)"></span> Verfügbar</span>
                <span><span class="legend-dot" style="background: rgba(220,53,69,.35)"></span> Gebucht</span>
                <span><span class="legend-dot" style="background: rgba(13,110,253,.35)"></span> Auswahl</span>
                <span><span class="legend-dot" style="background: #e9ecef"></span> Deaktiviert</span>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="col-md-5">
            <h2><?= htmlspecialchars($parking['title']) ?></h2>

            <?php
                $districtName = '';
                $neighborhoodName = '';
                if (!empty($parking['district_id'])) {
                    $dq = $conn->prepare("SELECT name FROM districts WHERE id = ? LIMIT 1");
                    $did = (int)$parking['district_id'];
                    $dq->bind_param('i', $did);
                    $dq->execute();
                    $dres = $dq->get_result();
                    if ($dr = $dres->fetch_assoc()) $districtName = $dr['name'];
                    $dq->close();
                }
                if (!empty($parking['neighborhood_id'])) {
                    $nq = $conn->prepare("SELECT name FROM neighborhoods WHERE id = ? LIMIT 1");
                    $nid = (int)$parking['neighborhood_id'];
                    $nq->bind_param('i', $nid);
                    $nq->execute();
                    $nres = $nq->get_result();
                    if ($nr = $nres->fetch_assoc()) $neighborhoodName = $nr['name'];
                    $nq->close();
                }
            ?>
            <p class="text-muted">
                <strong>Distrikt:</strong> <?= htmlspecialchars($districtName ?: '—') ?> <br>
                <strong>Stadtteil:</strong> <?= htmlspecialchars($neighborhoodName ?: '—') ?>
            </p>

            <h4 class="text-primary">
                €<?= number_format((float)$parking['price'], 2) ?> / day
            </h4>

            <?php if (!empty($parking['owner_name'])): ?>
                <p><strong>Owner:</strong> <?= htmlspecialchars($parking['owner_name']) ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" action="favorite_toggle.php" class="mb-2">
                    <input type="hidden" name="parking_id" value="<?= $id ?>">
                    <?php if ($isFavorite): ?>
                        <input type="hidden" name="action" value="remove">
                        <button class="btn btn-sm btn-outline-danger">♥ Entfernen</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                        <button class="btn btn-sm btn-outline-primary">♡ Favorisieren</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <!-- Ratings summary -->
            <div class="mb-2">
                <strong>Rating:</strong>
                <?php if ($reviewCount > 0): ?>
                    <span class="text-warning">★ <?= number_format($avgRating, 1) ?></span>
                    <small class="text-muted">(<?= $reviewCount ?>)</small>
                <?php else: ?>
                    <small class="text-muted">No ratings yet</small>
                <?php endif; ?>
            </div>

            <hr>

            <h5>Description</h5>
            <p><?= nl2br(htmlspecialchars($parking['description'])) ?></p>

            <hr>

            <?php if ($start_date && $end_date): ?>
                <div class="alert alert-info">
                    Selected: <strong><?= htmlspecialchars($start_date) ?></strong> → <strong><?= htmlspecialchars($end_date) ?></strong>
                </div>

                <form method="POST" action="book.php">
                    <input type="hidden" name="parking_id" value="<?= $id ?>">

                    <label>Start date</label>
                    <input type="date" name="booking_start" class="form-control mb-2"
                           value="<?= htmlspecialchars($start_date) ?>"
                           min="<?= htmlspecialchars($today) ?>" required>

                    <label>End date</label>
                    <input type="date" name="booking_end" class="form-control mb-2"
                           value="<?= htmlspecialchars($end_date) ?>"
                           min="<?= htmlspecialchars($today) ?>" required>

                    <button class="btn btn-primary w-100">Book now</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Select start and end date in the calendar.</p>
            <?php endif; ?>

            <hr>

            <!-- Reviews list -->
            <div class="mt-3">
                <h5>Reviews</h5>
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">No reviews yet. Be the first to review this parking.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <div><strong><?= htmlspecialchars($r['username']) ?></strong></div>
                                <div class="text-warning">★ <?= (int)$r['rating'] ?></div>
                            </div>
                            <?php if (!empty($r['comment'])): ?>
                                <div class="mt-1"><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
                            <?php endif; ?>
                            <small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Review form (only for logged-in users) -->
            <div class="mt-3">
                <h6>Add / Update your review</h6>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <p class="text-muted">Please <a href="login.php">login</a> to leave a review.</p>
                <?php else: ?>
                    <form method="POST" action="review_submit.php">
                        <input type="hidden" name="parking_id" value="<?= $id ?>">

                        <div class="mb-2">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" required>
                                <?php for ($s=1;$s<=5;$s++): ?>
                                    <option value="<?= $s ?>" <?= ($userReview && (int)$userReview['rating'] === $s) ? 'selected' : '' ?>><?= $s ?> ★</option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Comment (optional)</label>
                            <textarea name="comment" class="form-control" rows="3" maxlength="500"><?= $userReview ? htmlspecialchars($userReview['comment']) : '' ?></textarea>
                        </div>

                        <button class="btn btn-outline-primary">Submit review</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
