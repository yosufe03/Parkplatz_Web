<?php
$today = date('Y-m-d');

$location = $_GET['location'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
?>

<form method="GET" action="search.php" class="row g-3 justify-content-center mt-4">
    <div class="col-md-4">
        <label class="form-label">Ort</label>
        <input type="text"
               class="form-control"
               name="location"
               placeholder="Ort eingeben"
               value="<?= htmlspecialchars($location) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Von</label>
        <input type="date"
               class="form-control"
               name="from"
               min="<?= htmlspecialchars($today) ?>"
               value="<?= htmlspecialchars($from) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Bis</label>
        <input type="date"
               class="form-control"
               name="to"
               min="<?= htmlspecialchars($today) ?>"
               value="<?= htmlspecialchars($to) ?>">
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit">
            Suchen
        </button>
    </div>
</form>
