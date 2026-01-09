<?php
// Initialize basic variables
$today = date('Y-m-d');
$error = "";
$districts = [];
$neighborhoods = [];

// Load parking data (returns all form fields with defaults)
if (isset($parkingId) && isset($userId)) {
    $data = load_parking_data($parkingId, $userId);
    if ($data) {
        extract($data);
    }
} else {
    // Create mode - load_parking_data will provide defaults
    $data = [
        'parkingId' => null,
        'title' => "",
        'description' => "",
        'price' => "",
        'available_from' => "",
        'available_to' => "",
        'district_id' => null,
        'neighborhood_id' => null,
        'images' => [],
        'parking' => null
    ];
    extract($data);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = $_POST['price'] ?? "";
    $district_id = (int)($_POST['district_id'] ?? 0) ?: null;
    $neighborhood_id = (int)($_POST['neighborhood_id'] ?? 0) ?: null;
    $available_from = $_POST['available_from'] ?? "";
    $available_to = $_POST['available_to'] ?? "";

    // Save draft - no validation
    if (isset($_POST['save_draft_btn'])) {
        $savedParkingId = save_draft_parking($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $parkingId ?? null);
        header('Location: parking_edit.php?id=' . $savedParkingId);
        exit;
    }

    // Publish - with validation
    if (isset($_POST['publish'])) {
        $error = validate_parking($available_from, $available_to, $district_id, $neighborhood_id, $parkingId ?? null);

        // If no errors, publish
        if (!$error) {
            publish_parking($userId, $title, $description, $price, $district_id, $neighborhood_id, $available_from, $available_to, $parkingId ?? null);
            header("Location: my_parkings.php");
            exit;
        }
    }
}

// Load districts
if (empty($districts)) {
    $dstmt = $conn->prepare("SELECT * FROM districts ORDER BY name ASC");
    $dstmt->execute();
    $districts = $dstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dstmt->close();
}

// Load neighborhoods
if (isset($district_id) && $district_id > 0) {
    $nstmt = $conn->prepare("SELECT * FROM neighborhoods WHERE district_id = ? ORDER BY name ASC");
    $nstmt->bind_param('i', $district_id);
    $nstmt->execute();
    $neighborhoods = $nstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $nstmt->close();
}
?>

<form method="POST" enctype="multipart/form-data" class="mt-4">

<!-- Error Messages -->
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['upload_error'])): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($_SESSION['upload_error']) ?></div>
    <?php unset($_SESSION['upload_error']); ?>
<?php endif; ?>

<!-- Form Fields -->
<div class="mb-3">
    <label for="title">Titel</label>
    <input type="text" name="title" id="title" class="form-control" required value="<?= htmlspecialchars($title) ?>">
</div>

<div class="mb-3">
    <label for="description">Beschreibung</label>
    <textarea name="description" id="description" class="form-control" rows="4" required><?= htmlspecialchars($description) ?></textarea>
</div>

<div class="mb-3">
    <label for="price">Preis (€ pro Tag)</label>
    <input type="number" step="0.01" name="price" id="price" class="form-control" required value="<?= htmlspecialchars($price) ?>">
</div>

<div class="mb-3">
    <label>Distrikt</label>
    <div class="d-flex gap-2">
        <select name="district_id" class="form-select" required>
            <option value="">-- auswählen --</option>
            <?php foreach ($districts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($district_id == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" formnovalidate name="refresh" class="btn btn-outline-secondary">↻</button>
    </div>
</div>

<div class="mb-3">
    <label>Stadtteil</label>
    <select name="neighborhood_id" class="form-select" required>
        <option value="">-- auswählen --</option>
        <?php foreach ($neighborhoods as $n): ?>
            <option value="<?= $n['id'] ?>" <?= ($neighborhood_id == $n['id']) ? 'selected' : '' ?>><?= htmlspecialchars($n['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="mb-3">
    <label>Verfügbarkeit</label>
    <div class="row g-2">
        <div class="col-md-6">
            <input type="date" name="available_from" class="form-control" required value="<?= htmlspecialchars($available_from) ?>">
        </div>
        <div class="col-md-6">
            <input type="date" name="available_to" class="form-control" required value="<?= htmlspecialchars($available_to) ?>">
        </div>
    </div>
</div>
<!-- Images -->
<div class="mb-3">
    <label>Bilder (mind. 1, max. 5)</label>
    <?php
        $slots = $images + array_fill(0, 5, null);
        if (!empty($parkingId)) echo '<input type="hidden" name="id" value="' . (int)$parkingId . '">';
    ?>
    <div class="row g-2">
        <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="col-md-4">
                <div style="border:1px dashed #ddd; height:100px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                    <?php if ($slots[$i]): ?>
                        <img src="<?= htmlspecialchars($slots[$i]) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span style="color:#999;">Kein Bild</span>
                    <?php endif; ?>
                </div>
                <input type="file" name="images[]" class="form-control form-control-sm mt-2" accept="image/*">
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" formnovalidate name="save_draft_btn" value="<?= $i ?>" class="btn btn-sm btn-primary" formaction="/upload_image.php">Upload</button>
                    <?php if ($slots[$i]): ?>
                        <button type="submit" formnovalidate name="delete_existing_file" value="<?= basename($slots[$i]) ?>" class="btn btn-sm btn-danger" formaction="/upload_image.php">×</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Buttons -->
<div class="mb-3">
    <button type="submit" name="save_draft_btn" formnovalidate class="btn btn-info">Save</button>
    <button type="submit" name="publish" class="btn btn-success">Publish</button>
    <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-secondary">Abbrechen</a>
</div>

</form>
