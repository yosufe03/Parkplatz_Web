<?php
session_start();
include("includes/db_connect.php");

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$parking_id = isset($_POST['parking_id']) ? (int)$_POST['parking_id'] : 0;
$start_date = $_POST['booking_start'] ?? '';
$end_date   = $_POST['booking_end'] ?? '';

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

function fail($msg) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Buchung fehlgeschlagen</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="text-danger mb-3">❌ Buchung fehlgeschlagen</h3>
                <p class="mb-4"><?= htmlspecialchars($msg) ?></p>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">Zurück</a>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if ($parking_id <= 0 || !isValidDate($start_date) || !isValidDate($end_date)) {
    fail("Ungültige Buchungsdaten.");
}

$today = date('Y-m-d');

// No past booking
if ($start_date < $today || $end_date < $today) {
    fail("Buchungen in der Vergangenheit sind nicht erlaubt.");
}

if ($end_date < $start_date) {
    fail("Enddatum muss nach dem Startdatum liegen.");
}

/* 0) Fetch parking info + snapshot price */
$pStmt = $conn->prepare("SELECT title, price FROM parkings WHERE id = ?");
$pStmt->bind_param("i", $parking_id);
$pStmt->execute();
$pRes = $pStmt->get_result();
if ($pRes->num_rows === 0) {
    $pStmt->close();
    fail("Parking not found.");
}
$pRow = $pRes->fetch_assoc();
$pStmt->close();

$title = $pRow['title'] ?? 'Parking';
$price_day = (float)$pRow['price']; // snapshot price at booking time

/* 1) Check availability: every day in range must be covered */
$availCheck = $conn->prepare("
    SELECT COUNT(*) AS ok
    FROM parking_availability
    WHERE parking_id = ?
      AND available_from <= ?
      AND available_to   >= ?
");

$rangeStart = new DateTime($start_date);
$rangeEnd   = new DateTime($end_date);

$cursor = clone $rangeStart;
while ($cursor <= $rangeEnd) {
    $d = $cursor->format('Y-m-d');

    $availCheck->bind_param("iss", $parking_id, $d, $d);
    $availCheck->execute();
    $row = $availCheck->get_result()->fetch_assoc();

    if ((int)$row['ok'] === 0) {
        $availCheck->close();
        fail("Nicht verfügbar am Datum: $d");
    }

    $cursor->modify('+1 day');
}
$availCheck->close();

/* 2) Prevent overlap with existing bookings */
$confStmt = $conn->prepare("
    SELECT COUNT(*) AS conflicts
    FROM bookings
    WHERE parking_id = ?
      AND booking_start <= ?
      AND booking_end   >= ?
");
$confStmt->bind_param("iss", $parking_id, $end_date, $start_date);
$confStmt->execute();
$conf = $confStmt->get_result()->fetch_assoc();
$confStmt->close();

if ((int)$conf['conflicts'] > 0) {
    fail("Dieser Zeitraum ist bereits gebucht.");
}

/* 3) Insert booking WITH snapshot price_day */
$ins = $conn->prepare("
    INSERT INTO bookings (parking_id, user_id, booking_start, booking_end, price_day)
    VALUES (?, ?, ?, ?, ?)
");
$ins->bind_param("iissd", $parking_id, $user_id, $start_date, $end_date, $price_day);

if (!$ins->execute()) {
    $ins->close();
    fail("Fehler beim Speichern der Buchung.");
}
$booking_id = $ins->insert_id;
$ins->close();

/* Compute total using snapshot price */
$days = (new DateTime($start_date))->diff(new DateTime($end_date))->days + 1;
$totalPrice = $days * $price_day;
?>
<!DOCTYPE html>
<html lang="de">
<?php
    $pageTitle = "Buchung bestätigt";
    include("includes/header.php");
?>

<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="text-success mb-3">✅ Buchung bestätigt!</h3>

            <p class="mb-1"><strong>Parkplatz:</strong> <?= htmlspecialchars($title) ?></p>
            <p class="mb-1"><strong>Zeitraum:</strong> <?= htmlspecialchars($start_date) ?> → <?= htmlspecialchars($end_date) ?></p>
            <p class="mb-1"><strong>Tage:</strong> <?= (int)$days ?></p>
            <p class="mb-1"><strong>Preis/Tag (fix):</strong> €<?= number_format($price_day, 2) ?></p>
            <p class="mb-3"><strong>Gesamt:</strong> €<?= number_format($totalPrice, 2) ?></p>

            <p class="mb-3"><strong>Buchungsnummer:</strong> #<?= (int)$booking_id ?></p>

            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="parking.php?id=<?= (int)$parking_id ?>&booked=1">Zurück zum Parkplatz</a>
                <a class="btn btn-outline-secondary" href="my_bookings.php">Meine Buchungen</a>
            </div>

            <hr class="my-4">

            <small class="text-muted">
                Hinweis: Der Preis/Tag wird bei Buchung gespeichert und ändert sich nicht, auch wenn der Parkplatzpreis später angepasst wird.
            </small>
        </div>
    </div>
</div>

</body>
</html>
