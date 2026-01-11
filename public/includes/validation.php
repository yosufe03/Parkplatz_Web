<?php
include "db_connect.php";
/**
 * Validation Utilities
 * Common validation functions for forms and data
 */

function validate_parking($title, $description, $price, $start_date, $end_date, $district_id, $neighborhood_id, $parking_id = null) {
    $errors = [];

    foreach ([
        is_valid_title($title),
        is_valid_description($description),
        is_valid_price($price),
        is_valid_district($district_id),
        is_valid_neighborhood($neighborhood_id, $district_id),
        is_valid_date_range($start_date, $end_date),
        is_valid_images($parking_id),
        is_valid_parking_id($parking_id)
     ] as $err) {

        if (is_string($err)) {
            $errors[] = $err;
        }
    }

    return $errors;
}

function is_valid_title($title) {
    if (!is_string($title)) {
        return "Der Titel muss ein gültiger Text sein.";
    }

    $title = trim($title);
    $length = strlen($title);

    if ($length < 5) {
        return "Der Titel muss mindestens 5 Zeichen lang sein.";
    }
    if ($length > 100) {
        return "Der Titel darf maximal 100 Zeichen lang sein.";
    }

    return null;
}

function is_valid_description($description) {
    if (!is_string($description)) {
        return "Die Beschreibung muss ein gültiger Text sein.";
    }

    $description = trim($description);
    $length = strlen($description);

    if ($length < 10) {
        return "Die Beschreibung muss mindestens 10 Zeichen lang sein.";
    }
    if ($length > 1000) {
        return "Die Beschreibung darf maximal 1000 Zeichen lang sein.";
    }

    return null;
}

function is_valid_price($price) {
    if (!is_numeric($price)) {
        return "Der Preis muss eine gültige Zahl sein.";
    }

    $price = (float)$price;

    if ($price < 0) {
        return "Der Preis darf nicht negativ sein.";
    }

    return null;
}

function is_valid_district($district_id) {
    global $conn;
    if (!is_numeric($district_id)) {
        return "Bitte einen gültigen Bezirk auswählen.";
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM districts
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $district_id);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        return "Ungültiger Bezirk ausgewählt.";
    }

    return null;
}

function is_valid_neighborhood($neighborhood_id, $district_id) {
    global $conn;
    if (!is_numeric($neighborhood_id) || !is_numeric($district_id)) {
        return "Bitte einen gültigen Stadtteil auswählen.";
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM neighborhoods
        WHERE id = ? AND district_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $neighborhood_id, $district_id);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        return "Ungültiger Stadtteil für den ausgewählten Bezirk.";
    }

    return null;
}

function is_valid_date_range($start_date, $end_date) {
    if (!is_valid_date($start_date) || !is_valid_date($end_date)) {
        return "Bitte gültige Datumsangaben auswählen.";
    }

    $today = date('Y-m-d');

    if (!is_valid_date($start_date) || !is_valid_date($end_date)) {
        return "Bitte gültige Verfügbarkeitsdaten auswählen.";
    }
    if ($start_date < $today) {
        return "Verfügbarkeit darf nicht in der Vergangenheit liegen.";
    }
    if ($end_date < $start_date) {
        return "Das Enddatum muss nach dem Startdatum liegen.";
    }

    return null;
}

function is_valid_images($parking_id) {
    $existingImages = count(get_image_files($parking_id));

    if ($existingImages == 0) {
        return "Bitte mindestens ein Bild hochladen.";
    }
    if ($existingImages > 5) {
        return "Maximal 5 Bilder erlaubt.";
    }

    return null;
}

/**
 * Validate integer ID is positive
 * @param mixed $id The ID to validate
 * @return bool True if valid positive integer, false otherwise
 */
function is_valid_parking_id($id) {
    if (!is_numeric($id)) {
        return "Parking Id muss eine Zahl sein";
    }

    $id = (int)$id;
    if ($id <= 0) {
        return "Parking Id muss positiv sein"; // new parking
    }

    return null;
}

/**
 * Validate YYYY-MM-DD date string
 * @param string $date The date string to validate
 * @return bool True if valid date format, false otherwise
 */
function is_valid_date($date) {
    if (!is_string($date)) {
        return false;
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate booking doesn't violate business rules
 * @param string $start_date Booking start date
 * @param string $end_date Booking end date
 * @param object $conn Database connection
 * @param int $parking_id Parking ID
 * @param int $user_id User ID (optional, to prevent self-booking)
// * @return array ['valid' => bool, 'message' => string]
 */
function validate_booking($start_date, $end_date, $parking_id, $user_id) {
    global $conn;

    // 1) Date validity (your existing validator)
    $is_valid_date_range = is_valid_date_range($start_date, $end_date);
    if (is_string($is_valid_date_range)) {
        return $is_valid_date_range;
    }

    // 2) Parking exists + approved
    $stmt = $conn->prepare("
        SELECT owner_id, status
        FROM parkings
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $parking_id);
    $stmt->execute();
    $parking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$parking) {
        return "Parkplatz nicht gefunden.";
    }

    if (($parking['status'] ?? '') !== 'approved') {
        return "Parkplatz ist nicht verfügbar.";
    }

    // 3) Prevent self-booking
    if ((int)$parking['owner_id'] === (int)$user_id) {
        return "Sie können Ihren eigenen Parkplatz nicht buchen.";
    }

    // 4) Availability check (single range query)
    // There must exist at least one availability row that fully covers the requested range
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS ok
        FROM parking_availability
        WHERE parking_id = ?
          AND available_from <= ?
          AND available_to   >= ?
        LIMIT 1
    ");
    $stmt->bind_param('iss', $parking_id, $start_date, $end_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)($row['ok'] ?? 0) === 0) {
        return "Der gewünschte Zeitraum ist nicht verfügbar.";
    }

    // 5) Booking conflict check (single overlap query)
    // Overlap rule: existing.start <= requested.end AND existing.end >= requested.start
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS conflicts
        FROM bookings
        WHERE parking_id = ?
          AND booking_start <= ?
          AND booking_end   >= ?
        LIMIT 1
    ");
    $stmt->bind_param('iss', $parking_id, $end_date, $start_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)($row['conflicts'] ?? 0) > 0) {
        return "Dieser Zeitraum ist bereits gebucht.";
    }

    return null; // ✅ valid
}

/**
 * Check if user is admin
 */
function is_admin($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($result['role'] ?? null) === 'admin';
}
