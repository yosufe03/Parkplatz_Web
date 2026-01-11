<?php
$today = date('Y-m-d');
$district_id = $district_id ?? 0;
$neighborhood_id = $neighborhood_id ?? 0;
$from = $from ?? '';
$to = $to ?? '';

$districts = get_districts();
$neighborhoods = get_neighborhoods_for_district($district_id);
?>

<form method="GET" action="search.php" class="row g-3 justify-content-center mt-4">
    <div class="col-md-3">
        <label class="form-label">Distrikt</label>
        <div class="d-flex gap-2">
            <select name="district_id" class="form-select">
                <option value="0">-- Alle Distrikte --</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?php if ($district_id === (int)$d['id']) echo 'selected'; ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary">Aktualisieren</button>
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Stadtteil</label>
        <select name="neighborhood_id" class="form-select">
            <option value="0">-- Alle Stadtteile --</option>
            <?php foreach ($neighborhoods as $n): ?>
                <option value="<?= (int)$n['id'] ?>" <?php if ($neighborhood_id === (int)$n['id']) echo 'selected'; ?>>
                    <?= htmlspecialchars($n['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Von</label>
        <input type="date" class="form-control" name="from" min="<?= htmlspecialchars($today) ?>" value="<?= htmlspecialchars($from) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Bis</label>
        <input type="date" class="form-control" name="to" min="<?= htmlspecialchars($today) ?>" value="<?= htmlspecialchars($to) ?>">
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit">Suchen</button>
    </div>
</form>

