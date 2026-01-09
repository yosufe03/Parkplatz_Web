<?php
// Database connection
include "db_connect.php";

// Shared parking helpers for ParkShare app

/**
 * Validate YYYY-MM-DD date string
 */
function isValidDate($date)
{
    if (!is_string($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Ensure directory exists (recursive)
 */
function ensure_dir($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

/**
 * Return filesystem upload dir for a parking id (with trailing slash)
 */
function get_upload_dir($parkingId)
{
    // This file lives in public/includes, uploads live in public/uploads/parkings/
    return __DIR__ . '/../uploads/parkings/' . intval($parkingId) . '/';
}

/**
 * Return web-relative path prefix for images of a parking id (no leading slash)
 */
function get_upload_web_prefix($parkingId)
{
    return 'uploads/parkings/' . intval($parkingId) . '/';
}

/**
 * Get image file web paths (jpg/png) for a parking id sorted by filename
 * Returns array of web paths like 'uploads/parkings/123/1.jpg'
 */
function get_image_files($parkingId)
{
    $dir = get_upload_dir($parkingId);
    if (!is_dir($dir)) return [];

    $files = glob($dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE) ?: [];
    sort($files, SORT_NATURAL);

    $webPrefix = get_upload_web_prefix($parkingId);
    $result = [];
    foreach ($files as $f) {
        $result[] = $webPrefix . basename($f);
    }
    return $result;
}

/**
 * Return next image number to use for filename (1..N). Uses existing files to compute max.
 */
function next_image_number($parkingId)
{
    $dir = get_upload_dir($parkingId);
    if (!is_dir($dir)) return 1;
    $files = glob($dir . '*');
    $max = 0;
    foreach ($files as $f) {
        $name = pathinfo($f, PATHINFO_FILENAME);
        if (ctype_digit($name)) {
            $n = intval($name);
            if ($n > $max) $max = $n;
        }
    }
    return $max + 1;
}

/**
 * Delete all files under a parking upload dir and remove the directory.
 */
function delete_dir_contents($parkingId)
{
    $dir = get_upload_dir($parkingId);
    if (!is_dir($dir)) return;
    $files = glob($dir . '*');
    foreach ($files as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

/**
 * Check whether current user is owner of parking or is admin.
 * Returns true/false.
 */
function can_edit_parking($conn, $parkingId, $userId, $isAdmin)
{
    if ($isAdmin) return true;
    $sql = "SELECT user_id, status FROM parkings WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $parkingId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    // owners can edit only drafts
    if ($row['user_id'] == $userId && $row['status'] === 'draft') return true;
    return false;
}

/**
 * Internal: Save parking data (used by save_draft_parking and publish_parking)
 */
function _save_parking_data($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $status, $parkingId = null)
{
    global $conn;

    $priceFloat = is_numeric($price) ? (float)$price : 0.0;
    $district_id = $district_id ? (int)$district_id : null;
    $neighborhood_id = $neighborhood_id ? (int)$neighborhood_id : null;

    // Update or insert parking
    if ($parkingId) {
        $stmt = $conn->prepare("UPDATE parkings SET title=?, description=?, price=?, district_id=?, neighborhood_id=?, status=? WHERE id=? AND owner_id=?");
        $stmt->bind_param('ssdiisii', $title, $description, $priceFloat, $district_id, $neighborhood_id, $status, $parkingId, $userId);
        $finalParkingId = $parkingId;
    } else {
        $stmt = $conn->prepare("INSERT INTO parkings (owner_id, title, description, price, district_id, neighborhood_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issdiis', $userId, $title, $description, $priceFloat, $district_id, $neighborhood_id, $status);
        $finalParkingId = null;
    }

    $stmt->execute();
    if (!$parkingId) {
        $finalParkingId = (int)$stmt->insert_id;
    }
    $stmt->close();

    // Save availability
    $conn->query("DELETE FROM parking_availability WHERE parking_id = $finalParkingId");
    if ($available_from && $available_to && isValidDate($available_from) && isValidDate($available_to)) {
        $stmt = $conn->prepare("INSERT INTO parking_availability (parking_id, available_from, available_to) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $finalParkingId, $available_from, $available_to);
        $stmt->execute();
        $stmt->close();
    }

    return $finalParkingId;
}

/**
 * Save parking as draft
 * Returns the parking ID
 */
function save_draft_parking($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $parkingId = null)
{
    return _save_parking_data($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, 'draft', $parkingId);
}

/**
 * Publish parking (set status to pending)
 * Returns the parking ID
 */
function publish_parking($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $parkingId = null)
{
    return _save_parking_data($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, 'pending', $parkingId);
}

/**
 * Load parking data for a given parking ID and user
 * Returns array with parking info and form fields, or null if not found/unauthorized
 */
function load_parking_data($parkingId, $userId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $userId = (int)$userId;

    $stmt = $conn->prepare("SELECT p.*, pa.available_from, pa.available_to
                            FROM parkings p
                            LEFT JOIN parking_availability pa ON p.id = pa.parking_id
                            WHERE p.id = ? AND p.owner_id = ? LIMIT 1");
    $stmt->bind_param('ii', $parkingId, $userId);
    $stmt->execute();
    $parking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$parking) {
        return null;
    }

    return [
        'parking' => $parking,
        'parkingId' => $parkingId,
        'title' => $parking['title'] ?? '',
        'description' => $parking['description'] ?? '',
        'price' => $parking['price'] ?? '',
        'district_id' => $parking['district_id'] ?? null,
        'neighborhood_id' => $parking['neighborhood_id'] ?? null,
        'available_from' => $parking['available_from'] ?: '',
        'available_to' => $parking['available_to'] ?: '',
        'images' => get_image_files($parkingId)
    ];
}

/**
 * Validate parking data before publishing
 * Returns error message string, or null if valid
 */
function validate_parking($available_from, $available_to, $district_id, $neighborhood_id, $parkingId)
{
    global $conn, $today;

    // Validate dates
    if (!isValidDate($available_from) || !isValidDate($available_to)) {
        return "Bitte gültige Verfügbarkeitsdaten auswählen.";
    }
    if ($available_from < $today) {
        return "Verfügbarkeit darf nicht in der Vergangenheit liegen.";
    }
    if ($available_to < $available_from) {
        return "Das Enddatum muss nach dem Startdatum liegen.";
    }

    // Validate neighborhood belongs to district
    $stmt = $conn->prepare("SELECT district_id FROM neighborhoods WHERE id = ?");
    $stmt->bind_param('i', $neighborhood_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (int)$row['district_id'] !== $district_id) {
        return "Gewählter Stadtteil gehört nicht zum ausgewählten Distrikt.";
    }

    // Validate images
    $existingImages = $parkingId ? get_image_files($parkingId) : [];
    $newUploads = !empty($_FILES['images']['tmp_name']) ? count(array_filter($_FILES['images']['tmp_name'])) : 0;
    $totalImages = count($existingImages) + $newUploads;

    if ($totalImages === 0) {
        return "Bitte mindestens ein Bild hochladen.";
    }
    if ($totalImages > 5) {
        return "Maximal 5 Bilder erlaubt.";
    }

    return null;
}

/**
 * Get district name by ID
 */
function get_district_name($districtId)
{
    global $conn;

    if (!$districtId) return '';

    $stmt = $conn->prepare("SELECT name FROM districts WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $districtId);
    $stmt->execute();
    $name = $stmt->get_result()->fetch_assoc()['name'] ?? '';
    $stmt->close();

    return $name;
}

/**
 * Get neighborhood name by ID
 */
function get_neighborhood_name($neighborhoodId)
{
    global $conn;

    if (!$neighborhoodId) return '';

    $stmt = $conn->prepare("SELECT name FROM neighborhoods WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $neighborhoodId);
    $stmt->execute();
    $name = $stmt->get_result()->fetch_assoc()['name'] ?? '';
    $stmt->close();

    return $name;
}

?>
