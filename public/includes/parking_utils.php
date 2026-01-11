<?php
/**
 * Parking Utilities
 * Shared functions for parking management, image uploads, and validation
 */

// Database connection
include "db_connect.php";
include "security.php";
include "validation.php";

// Shared parking helpers for ParkShare app

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
    if ($available_from && $available_to && is_valid_date($available_from) && is_valid_date($available_to)) {
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

function upload_parking_image($slot, $parkingId)
{
        if (!$parkingId) {
            return false;
        }

    if (
        !isset($_FILES['images']['tmp_name'][$slot]) ||
        !is_uploaded_file($_FILES['images']['tmp_name'][$slot])
    ) {
        return false;
    }

    $uploadDir = get_upload_dir($parkingId);
    ensure_dir($uploadDir);

    // Find highest existing number
    $existing = glob($uploadDir . "*.{jpg,jpeg,png}", GLOB_BRACE) ?: [];
    $maxNum = 0;

    foreach ($existing as $file) {
        $filename = basename($file);        // e.g. "12.jpg"
        $number   = (int) explode('.', $filename)[0]; // take part before "."

        $maxNum = max($maxNum, $number);
    }

    $ext = strtolower(
        pathinfo($_FILES['images']['name'][$slot], PATHINFO_EXTENSION)
    );

    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return false;
    }

    $target = $uploadDir . (++$maxNum) . '.' . $ext;

    return move_uploaded_file(
        $_FILES['images']['tmp_name'][$slot],
        $target
    );
}


/**
 * Deletes an uploaded image for a parking entry.
 *
 * @param int    $parkingId
 * @param string $filename   File name only (no path!)
 * @return bool              True if deleted, false otherwise
 */
function delete_parking_image(string $filename, int $parkingId): bool
{
    if (!$parkingId || !$filename) {
        return false;
    }

    $path = get_upload_dir($parkingId) . basename($filename);

    if (!is_file($path)) {
        return false;
    }

    return unlink($path);
}


/**
 * Delete a parking by ID
 */
function delete_parking($parkingId) {
    global $conn;
    $parkingId = (int)$parkingId;
    $stmt = $conn->prepare("DELETE FROM parkings WHERE id = ?");
    $stmt->bind_param("i", $parkingId);
    $stmt->execute();
    $stmt->close();
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

    $sql = "SELECT p.*, pa.available_from, pa.available_to
            FROM parkings p
            LEFT JOIN parking_availability pa ON p.id = pa.parking_id
            WHERE p.id = ? AND p.owner_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $parkingId, $userId);
    $stmt->execute();
    $parking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$parking) return null;

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

function get_districts()
{
    global $conn;
    $res = $conn->query("SELECT * FROM districts ORDER BY name ASC");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function get_neighborhoods_for_district($districtId)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM neighborhoods WHERE district_id = ? ORDER BY name ASC");
    $stmt->bind_param('i', $districtId);
    $stmt->execute();
    $neighborhoods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $neighborhoods;
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

/**
 * Get parking details including owner and availability
 * Returns parking record or null if not found
 */
function get_parking_by_id($parkingId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $stmt = $conn->prepare("
        SELECT p.*, u.username AS owner_name
        FROM parkings p
        LEFT JOIN users u ON p.owner_id = u.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $parkingId);
    $stmt->execute();
    $parking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $parking;
}

/**
 * Get average rating and review count for a parking
 */
function get_parking_rating($parkingId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM parking_reviews WHERE parking_id = ?");
    $stmt->bind_param("i", $parkingId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'avg_rating' => $result['avg_rating'] !== null ? (float)$result['avg_rating'] : 0.0,
        'review_count' => (int)$result['review_count']
    ];
}

/**
 * Get all reviews for a parking (limit 20)
 */
function get_parking_reviews($parkingId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $stmt = $conn->prepare("SELECT r.*, u.username FROM parking_reviews r JOIN users u ON r.user_id = u.id WHERE r.parking_id = ? ORDER BY r.created_at DESC LIMIT 20");
    $stmt->bind_param("i", $parkingId);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $reviews;
}

/**
 * Get user's review for a parking (if exists)
 */
function get_user_review($parkingId, $userId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $userId = (int)$userId;
    $stmt = $conn->prepare("SELECT rating, comment FROM parking_reviews WHERE parking_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $parkingId, $userId);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $review;
}

/**
 * Check if parking is in user's favorites
 */
function is_parking_favorite($parkingId, $userId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $userId = (int)$userId;
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE parking_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $parkingId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $isFavorite = ($result && $result->num_rows > 0);
    $stmt->close();

    return $isFavorite;
}

/**
 * Get all available dates for a parking as associative array
 * Returns: ['Y-m-d' => true, ...]
 */
function get_available_dates($parkingId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $availableDates = [];

    $stmt = $conn->prepare("SELECT available_from, available_to FROM parking_availability WHERE parking_id = ?");
    $stmt->bind_param("i", $parkingId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $s = new DateTime(substr($row['available_from'], 0, 10));
        $e = new DateTime(substr($row['available_to'], 0, 10));
        while ($s <= $e) {
            $availableDates[$s->format('Y-m-d')] = true;
            $s->modify('+1 day');
        }
    }
    $stmt->close();

    return $availableDates;
}

/**
 * Get all booked dates for a parking as associative array
 * Returns: ['Y-m-d' => true, ...]
 */
function get_booked_dates($parkingId)
{
    global $conn;

    $parkingId = (int)$parkingId;
    $bookedDates = [];

    $stmt = $conn->prepare("SELECT booking_start, booking_end FROM bookings WHERE parking_id = ?");
    $stmt->bind_param("i", $parkingId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $s = new DateTime($row['booking_start']);
        $e = new DateTime($row['booking_end']);
        while ($s <= $e) {
            $bookedDates[$s->format('Y-m-d')] = true;
            $s->modify('+1 day');
        }
    }
    $stmt->close();

    return $bookedDates;
}

/**
 * Get admin statistics
 */
function get_admin_stats() {
    global $conn;
    $sql = "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM parkings) as total_parkings,
        (SELECT COUNT(*) FROM bookings) as total_bookings,
        (SELECT COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day), 0) FROM bookings b) AS total_revenue,
        (SELECT COUNT(*) FROM bookings WHERE booking_start <= CURDATE() AND booking_end >= CURDATE()) as active_bookings,
        (SELECT COUNT(*) FROM parkings WHERE status = 'pending') as pending_count";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $stats;
}

/**
 * Get top users by earnings
 */
function get_top_users($limit = 10) {
    global $conn;
    $sql = "SELECT 
        u.username, 
        COUNT(p.id) as parking_count, 
        COALESCE(SUM((DATEDIFF(b.booking_end, b.booking_start) + 1) * b.price_day), 0) AS total_earnings 
        FROM users u 
        LEFT JOIN parkings p ON p.owner_id = u.id 
        LEFT JOIN bookings b ON b.parking_id = p.id 
        GROUP BY u.id 
        HAVING parking_count > 0 
        ORDER BY total_earnings DESC 
        LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $users;
}

/**
 * Get pending parkings
 */
function get_pending_parkings($limit = 10) {
    global $conn;
    $sql = "SELECT 
        p.id, 
        p.title, 
        p.price, 
        u.username 
        FROM parkings p 
        LEFT JOIN users u ON p.owner_id = u.id 
        WHERE p.status = 'pending' 
        ORDER BY p.created_at ASC 
        LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $parkings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $parkings;
}

/**
 * Add a district
 */
function add_district($name) {
    global $conn;
    $name = trim($name);
    if (!$name) return false;
    $stmt = $conn->prepare("INSERT INTO districts (name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Delete a district
 */
function delete_district($id) {
    global $conn;
    $id = (int)$id;
    $stmt = $conn->prepare("DELETE FROM districts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Add a neighborhood
 */
function add_neighborhood($district_id, $name) {
    global $conn;
    $district_id = (int)$district_id;
    $name = trim($name);
    if (!$name || $district_id <= 0) return false;
    $stmt = $conn->prepare("INSERT INTO neighborhoods (district_id, name) VALUES (?, ?)");
    $stmt->bind_param('is', $district_id, $name);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Delete a neighborhood
 */
function delete_neighborhood($id) {
    global $conn;
    $id = (int)$id;
    $stmt = $conn->prepare("DELETE FROM neighborhoods WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
