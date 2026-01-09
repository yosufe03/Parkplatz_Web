<?php
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
    return array_map(function ($f) use ($webPrefix) {
        return $webPrefix . basename($f);
    }, $files);
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
 * Save or update a draft parking with form data.
 * Returns the draft parking ID.
 */
function save_draft($conn, $userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $draftId = null)
{
    $priceFloat = is_numeric($price) ? (float)$price : 0.0;
    
    if ($draftId) {
        // Update existing draft (affected_rows can be 0 if values didn't change, so don't check it)
        error_log("save_draft: Attempting UPDATE for draftId=$draftId, userId=$userId");
        $stmt = $conn->prepare("UPDATE parkings SET title=?, description=?, price=?, district_id=?, neighborhood_id=? WHERE id=? AND owner_id=?");
        $stmt->bind_param('ssdiiii', $title, $description, $priceFloat, $district_id, $neighborhood_id, $draftId, $userId);
        $stmt->execute();
        $stmt->close();
        error_log("save_draft: UPDATE completed for draftId=$draftId");
    } else {
        // Create new draft
        $stmt = $conn->prepare("INSERT INTO parkings (owner_id, title, description, price, district_id, neighborhood_id, status) VALUES (?, ?, ?, ?, ?, ?, 'draft')");
        $stmt->bind_param('issdii', $userId, $title, $description, $priceFloat, $district_id, $neighborhood_id);
        $stmt->execute();
        $draftId = (int)$stmt->insert_id;
        $stmt->close();
    }
    
    // Save availability - only if both dates are valid
    $conn->query("DELETE FROM parking_availability WHERE parking_id = $draftId");
    if ($available_from && $available_to && isValidDate($available_from) && isValidDate($available_to)) {
        $stmt = $conn->prepare("INSERT INTO parking_availability (parking_id, available_from, available_to) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $draftId, $available_from, $available_to);
        $stmt->execute();
        $stmt->close();
    }
    
    return $draftId;
}

?>
