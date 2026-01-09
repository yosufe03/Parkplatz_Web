<?php
// parking_form.php
// Expects these variables to be present in the including file (or defaults will be used):
// $title, $description, $price, $available_from, $available_to
// $districts (array), $neighborhoods (array), $district_id, $neighborhood_id
// $today, $submitLabel (string), $returnUrl (string), $isAdmin (bool), $images (array of web paths)
// The form inputs use name attributes that match existing server handling (images[], delete_existing[])

if (!isset($submitLabel)) $submitLabel = 'Speichern';
if (!isset($returnUrl)) $returnUrl = 'dashboard.php';
// Ensure arrays exist
$districts = $districts ?? [];
$neighborhoods = $neighborhoods ?? [];

// Shared helpers and select population
// Provide isValidDate if not already defined
if (!function_exists('isValidDate')) {
    function isValidDate($d) {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }
}

// Determine selected district/neighborhood from POST/GET if caller didn't set them
if (!isset($district_id)) {
    $district_id = null;
    $neighborhood_id = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['district_id']) && (int)$_POST['district_id'] > 0) $district_id = (int)$_POST['district_id'];
        if (isset($_POST['neighborhood_id']) && (int)$_POST['neighborhood_id'] > 0) $neighborhood_id = (int)$_POST['neighborhood_id'];
    } else {
        if (isset($_GET['district_id']) && (int)$_GET['district_id'] > 0) $district_id = (int)$_GET['district_id'];
        if (isset($_GET['neighborhood_id']) && (int)$_GET['neighborhood_id'] > 0) $neighborhood_id = (int)$_GET['neighborhood_id'];
    }
    // If the including page provided $parking (edit page), use its values as defaults
    if ($district_id === null && isset($parking) && isset($parking['district_id'])) $district_id = (int)$parking['district_id'];
    if ($neighborhood_id === null && isset($parking) && isset($parking['neighborhood_id'])) $neighborhood_id = (int)$parking['neighborhood_id'];
}

// Load districts if not provided
if (empty($districts)) {
    $districts = [];
    if (isset($conn)) {
        $dstmt = $conn->prepare("SELECT * FROM districts ORDER BY name ASC");
        if ($dstmt) {
            $dstmt->execute();
            $dres = $dstmt->get_result();
            while ($d = $dres->fetch_assoc()) $districts[] = $d;
            $dstmt->close();
        }
    }
}

// Load neighborhoods depending on currently selected district if not provided
if (empty($neighborhoods)) {
    $neighborhoods = [];
    if (isset($conn)) {
        if ($district_id !== null && $district_id > 0) {
            $nstmt = $conn->prepare("SELECT * FROM neighborhoods WHERE district_id = ? ORDER BY name ASC");
            if ($nstmt) {
                $nstmt->bind_param('i', $district_id);
                $nstmt->execute();
                $nres = $nstmt->get_result();
                while ($n = $nres->fetch_assoc()) $neighborhoods[] = $n;
                $nstmt->close();
            }
        } else {
            $nstmt = $conn->prepare("SELECT * FROM neighborhoods ORDER BY name ASC");
            if ($nstmt) {
                $nstmt->execute();
                $nres = $nstmt->get_result();
                while ($n = $nres->fetch_assoc()) $neighborhoods[] = $n;
                $nstmt->close();
            }
        }
    }
}

?>

        <div class="mb-3">
            <label for="title" class="form-label">Titel</label>
            <input type="text" name="title" id="title" class="form-control" required
                   value="<?= htmlspecialchars($title ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Beschreibung</label>
            <textarea name="description" id="description" class="form-control" rows="4" required><?= htmlspecialchars($description ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Preis (€ pro Tag)</label>
            <input type="number" step="0.01" name="price" id="price" class="form-control" required
                   value="<?= htmlspecialchars($price ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Distrikt</label>
            <div class="d-flex">
                <select name="district_id" class="form-select me-2" required>
                    <option value="">-- auswählen --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (isset($district_id) && $district_id == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- no-JS fallback / GET refresh: reload page to update neighborhoods for selected district -->
                <!-- formnovalidate prevents HTML5 form validation from blocking this refresh button -->
                <?php if (isset($parkingId) && (int)$parkingId > 0): ?>
                    <input type="hidden" name="id" value="<?= (int)$parkingId ?>">
                <?php endif; ?>
                <?php
                // If there are server-side preview images stored in session, keep the preview flag when doing a GET refresh
                if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['preview_images'])):
                    // ensure the preview parameter is preserved so previews remain visible after Aktualisieren
                ?>
                    <input type="hidden" name="preview" value="1">
                <?php endif; ?>
                <button type="submit" formnovalidate name="refresh" value="1" class="btn btn-outline-secondary" title="Stadtteile laden">Aktualisieren</button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Stadtteil</label>
            <select name="neighborhood_id" class="form-select" required>
                <option value="">-- auswählen --</option>
                <?php foreach ($neighborhoods as $n): ?>
                    <option value="<?= (int)$n['id'] ?>" <?= (isset($neighborhood_id) && $neighborhood_id == $n['id']) ? 'selected' : '' ?>><?= htmlspecialchars($n['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Wird benötigt; bitte passenden Distrikt auswählen. Klicken Sie auf "Aktualisieren", um die Liste der Stadtteile für den gewählten Distrikt zu laden.</small>
        </div>

        <!-- Availability -->
        <div class="mb-3">
            <label class="form-label">Verfügbarkeit (Datum)</label>
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="available_from" class="form-label">Von</label>
                    <input type="date" name="available_from" id="available_from"
                           class="form-control" min="<?= htmlspecialchars($today ?? date('Y-m-d')) ?>"
                           value="<?= htmlspecialchars($available_from ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="available_to" class="form-label">Bis</label>
                    <input type="date" name="available_to" id="available_to"
                           class="form-control" min="<?= htmlspecialchars($today ?? date('Y-m-d')) ?>"
                           value="<?= htmlspecialchars($available_to ?? '') ?>">
                </div>
            </div>
            <small class="text-muted">Nur zukünftige Daten. Enddatum muss ≥ Startdatum sein. (Wird beim finalen Speichern validiert)</small>
        </div>

        <?php if (!isset($images)) { $images = []; } ?>
        <div class="mb-3">
            <label class="form-label">Bilder verwalten (mind. 1, max. 5)</label>
            <div class="row g-2">
                <?php
                    // prepare slots from existing images if present
                    $slots = array_fill(0, 5, null);
                    for ($i = 0; $i < 5; $i++) {
                        if (isset($images[$i])) $slots[$i] = $images[$i];
                    }
                ?>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mb-1">
                            <label class="form-label small">Bild <?= ($i+1) ?><?= $i === 0 ? ' (Hauptbild)' : '' ?></label>
                        </div>
                        <div class="img-box mb-1" id="img-box-<?= $i ?>">
                            <?php if ($slots[$i] !== null): ?>
                                <img src="<?= htmlspecialchars($slots[$i]) ?>" alt="Vorschau">
                            <?php else: ?>
                                <span class="text-muted">Kein Bild</span>
                                <img src="" alt="Vorschau" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="image_<?= $i ?>" name="images[]" class="form-control" accept="image/jpeg,image/png">
                        <div class="d-flex gap-2 mt-1">
                            <button type="submit" formnovalidate name="upload_slot" value="<?= $i ?>" class="btn btn-sm btn-primary" formaction="/upload_image.php" formmethod="post">Upload</button>
                            <?php if ($slots[$i] !== null): ?>
                                <button type="submit" formnovalidate name="delete_existing_file" value="<?= htmlspecialchars(basename($slots[$i])) ?>" class="btn btn-sm btn-danger" formaction="/upload_image.php" formmethod="post">Bild entfernen</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <small class="text-muted">Wählen Sie mindestens ein Bild (JPG/PNG). Die erste vorhandene oder neu ausgewählte Datei wird als Hauptbild verwendet.</small>
        </div>

        <?php if (!empty($isAdmin)): ?>
            <div class="mb-3">
                <label class="form-label">Besitzer ID</label>
                <input type="number" name="owner_id" class="form-control" value="<?= htmlspecialchars($parking['owner_id'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="pending" <?= (isset($parking['status']) && $parking['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= (isset($parking['status']) && $parking['status'] === 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= (isset($parking['status']) && $parking['status'] === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <button type="submit" class="btn btn-success"><?= htmlspecialchars($submitLabel) ?></button>
            <?php // Per-slot Upload buttons are provided next to each file input; global preview save removed. ?>
            <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-secondary">Abbrechen</a>
        </div>

        <style>
        /* Image preview boxes */
        .img-box { border:1px dashed #ced4da; background:#f8f9fa; height:140px; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative; }
        .img-box img { width:100%; height:100%; object-fit:cover; display:block; }
        .img-box span { color:#6c757d; }
        </style>

        <!-- No JavaScript: automatic preview-saving via auto-submit has been removed to satisfy strict no-JS requirement. -->
