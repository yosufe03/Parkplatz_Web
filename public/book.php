<?php
include_once("includes/parking_utils.php");
include_once("includes/validation.php"); // must contain validate_booking() + is_valid_date_range()
include_once("includes/header.php");

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$parking_id = (int)($_POST['parking_id'] ?? 0);
$start_date = $_POST['booking_start'] ?? '';
$end_date   = $_POST['booking_end'] ?? '';

$success = false;
$error   = null;

$title = '';
$price_day = 0.0;
$booking_id = null;
$days = 0;
$totalPrice = 0.0;

// 1) Validate booking rules (string|null)
$error = validate_booking($start_date, $end_date, $parking_id, $user_id);

// 2) If valid -> load snapshot + insert booking
if ($error === null) {

    // Snapshot title + price for display and to store price_day at booking time
    $stmt = $conn->prepare("SELECT title, price FROM parkings WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $error = "Datenbank Fehler.";
    } else {
        $stmt->bind_param("i", $parking_id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$p) {
            $error = "Parking nicht gefunden.";
        } else {
            $title = $p['title'] ?? 'Parking';
            $price_day = (float)($p['price'] ?? 0);

            // Insert booking with snapshot price_day
            $stmt = $conn->prepare("
                INSERT INTO bookings (parking_id, user_id, booking_start, booking_end, price_day)
                VALUES (?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $error = "Datenbank Fehler.";
            } else {
                $stmt->bind_param("iissd", $parking_id, $user_id, $start_date, $end_date, $price_day);

                if ($stmt->execute()) {
                    $booking_id = (int)$stmt->insert_id;
                    $success = true;

                    $days = (new DateTime($start_date))->diff(new DateTime($end_date))->days + 1;
                    $totalPrice = $days * $price_day;
                } else {
                    $error = "Fehler beim Speichern der Buchung.";
                }

                $stmt->close();
            }
        }
    }
}
?>

<div class="container py-5">

    <?php if ($success): ?>

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

    <?php else: ?>

        <div class="alert alert-danger">
            <h4 class="mb-2">❌ Buchung fehlgeschlagen</h4>
            <p class="mb-3"><?= htmlspecialchars($error ?? "Unbekannter Fehler") ?></p>
            <a href="javascript:history.back()" class="btn btn-outline-secondary">Zurück</a>
        </div>

    <?php endif; ?>

</div>