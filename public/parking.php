<?php
include("includes/db_connect.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("Invalid parking id.");

/* ---------- Helpers ---------- */
function normalizeMonthYear($month, $year) {
    if ($month < 1) { $month = 12; $year--; }
    if ($month > 12) { $month = 1; $year++; }
    return [$month, $year];
}
function isValidDate($d) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

/* ---------- Today ---------- */
$today = date('Y-m-d');

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

/* ---------- Images (your logic kept) ---------- */
$imageDir = "uploads/parkings/" . $parking['id'] . "/";
$images = glob($imageDir . "*.{jpg,jpeg,png}", GLOB_BRACE);
sort($images);

/* ---------- Availability ---------- */
$availability = [];
$availStmt = $conn->prepare("
    SELECT available_from, available_to
    FROM parking_availability
    WHERE parking_id = ?
");
$availStmt->bind_param("i", $id);
$availStmt->execute();
$availRes = $availStmt->get_result();
while ($row = $availRes->fetch_assoc()) {
    $availability[] = $row;
}
$availStmt->close();

/* ---------- Availability by DATE ---------- */
$availableDates = [];
foreach ($availability as $slot) {
    // If availability table is DATE-only, substr is harmless; it also works if DATETIME
    $start = new DateTime(substr($slot['available_from'], 0, 10));
    $end   = new DateTime(substr($slot['available_to'], 0, 10));
    while ($start <= $end) {
        $availableDates[$start->format('Y-m-d')] = true;
        $start->modify('+1 day');
    }
}

/* ---------- Booked dates (NEW) ---------- */
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
    $bs = new DateTime($b['booking_start']);
    $be = new DateTime($b['booking_end']);
    while ($bs <= $be) {
        $bookedDates[$bs->format('Y-m-d')] = true;
        $bs->modify('+1 day');
    }
}
$bookStmt->close();

/* ---------- Calendar ---------- */
$year  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
[$month, $year] = normalizeMonthYear($month, $year);

$firstDay = new DateTime("$year-$month-01");
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N');

/* ---------- Selected range ---------- */
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

$range_error = '';

if ($start_date && !isValidDate($start_date)) $start_date = null;
if ($end_date && !isValidDate($end_date)) $end_date = null;

if ($start_date && $start_date < $today) {
    $start_date = null;
    $end_date = null;
}
if ($end_date && $end_date < $today) {
    $end_date = null;
}

if ($start_date && $end_date && $end_date < $start_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

/* ---------- NEW: reject ranges that include booked or unavailable days ---------- */
if ($start_date && $end_date) {
    $cursor = new DateTime($start_date);
    $endObj = new DateTime($end_date);

    while ($cursor <= $endObj) {
        $dk = $cursor->format('Y-m-d');

        // Block booked days
        if (isset($bookedDates[$dk])) {
            $range_error = "Selected range contains already booked dates. Please choose another range.";
            $start_date = null;
            $end_date = null;
            break;
        }

        // (Recommended) Block days that are not available (not green)
        if (!isset($availableDates[$dk])) {
            $range_error = "Selected range contains unavailable dates. Please choose only available (green) dates.";
            $start_date = null;
            $end_date = null;
            break;
        }

        $cursor->modify('+1 day');
    }
}

/* ---------- Month navigation ---------- */
[$pm, $py] = normalizeMonthYear($month - 1, $year);
[$nm, $ny] = normalizeMonthYear($month + 1, $year);
?>

<!DOCTYPE html>
<html lang="de">
<?php
    $pageTitle = htmlspecialchars($parking['title']);
    include("includes/header.php");
?>
<style>
    .parking-img { width:100%; height:400px; object-fit:cover; border-radius:10px; }

    .calendar { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
    .calendar-day, .calendar-head {
        border:1px solid #ddd; border-radius:8px; padding:8px; text-align:right;
    }
    .calendar-head { background:#f8f9fa; font-weight:600; text-align:center; }

    .available { background:#e8f5e9; }
    .past { background:#f1f1f1; color:#999; }

    /* NEW: booked days */
    .booked { background:#ffeaea; border-color:#f3b5b5; color:#a33; }
    .booked a { pointer-events:none; }

    .in-range { background:#e7f1ff; }
    .range-start, .range-end { background:#0d6efd; color:#fff; }

    .calendar-day a { text-decoration:none; color:inherit; display:block; height:100%; }
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
                                <img src="<?= htmlspecialchars($img) ?>" class="parking-img">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h5>Availability</h5>

            <?php if (!empty($range_error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($range_error) ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-2">
                <a href="?id=<?= $id ?>&month=<?= $pm ?>&year=<?= $py ?>">←</a>
                <strong><?= $firstDay->format('F Y') ?></strong>
                <a href="?id=<?= $id ?>&month=<?= $nm ?>&year=<?= $ny ?>">→</a>
            </div>

            <div class="calendar mb-3">
                <?php foreach (['Mo','Di','Mi','Do','Fr','Sa','So'] as $d): ?>
                    <div class="calendar-head"><?= $d ?></div>
                <?php endforeach; ?>

                <?php for ($i=1;$i<$startWeekday;$i++): ?><div></div><?php endfor; ?>

                <?php for ($day=1;$day<=$daysInMonth;$day++):
                    $date = sprintf('%04d-%02d-%02d',$year,$month,$day);

                    $isPast   = $date < $today;
                    $isAvail  = isset($availableDates[$date]);
                    $isBooked = isset($bookedDates[$date]);

                    $classes = [];
                    if ($isAvail) $classes[]='available';
                    if ($isPast) $classes[]='past';
                    if ($isBooked) $classes[]='booked';

                    if ($start_date && $end_date && $date >= $start_date && $date <= $end_date) $classes[]='in-range';
                    if ($date === $start_date) $classes[]='range-start';
                    if ($date === $end_date) $classes[]='range-end';

                    // Disable click if past OR booked OR not available
                    if ($isPast || $isBooked || !$isAvail) {
                        $url = null;
                    } elseif (!$start_date) {
                        $url = "?id=$id&month=$month&year=$year&start_date=$date";
                    } elseif (!$end_date) {
                        $url = "?id=$id&month=$month&year=$year&start_date=$start_date&end_date=$date";
                    } else {
                        $url = "?id=$id&month=$month&year=$year&start_date=$date";
                    }
                    ?>
                    <div class="calendar-day <?= implode(' ',$classes) ?>">
                        <?php if ($url): ?><a href="<?= $url ?>"><?= $day ?></a><?php else: ?><?= $day ?><?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <small class="text-muted">
                Grün = verfügbar, Rot = gebucht, Grau = nicht auswählbar.
            </small>

        </div>

        <!-- RIGHT -->
        <div class="col-md-5">

            <h2><?= htmlspecialchars($parking['title']) ?></h2>

            <p class="text-muted">
                Location: <strong><?= htmlspecialchars($parking['location']) ?></strong>
            </p>

            <h4 class="text-primary">
                €<?= number_format($parking['price'],2) ?> / day
            </h4>

            <?php if (!empty($parking['owner_name'])): ?>
                <p><strong>Owner:</strong> <?= htmlspecialchars($parking['owner_name']) ?></p>
            <?php endif; ?>

            <hr>

            <h5>Description</h5>
            <p><?= nl2br(htmlspecialchars($parking['description'])) ?></p>

            <hr>

            <?php if ($start_date && $end_date): ?>
                <div class="alert alert-info">
                    Selected: <strong><?= $start_date ?></strong> → <strong><?= $end_date ?></strong>
                </div>

                <form method="POST" action="book.php">
                    <input type="hidden" name="parking_id" value="<?= $id ?>">

                    <label>Start date</label>
                    <input type="date" name="booking_start" class="form-control mb-2"
                           value="<?= $start_date ?>" min="<?= $today ?>" required>

                    <label>End date</label>
                    <input type="date" name="booking_end" class="form-control mb-2"
                           value="<?= $end_date ?>" min="<?= $today ?>" required>

                    <button class="btn btn-primary w-100">Book now</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Select start and end date in the calendar.</p>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
