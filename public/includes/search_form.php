<?php
$today = date('Y-m-d');
$district_id = $district_id ?? 0;
$neighborhood_id = $neighborhood_id ?? 0;
$from = $from ?? '';
$to = $to ?? '';

$districts = get_districts();
$neighborhoods = get_neighborhoods_for_district($district_id);
?>

<form method="GET" action="search.php" class="mt-4">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label">Distrikt</label>
            <select name="district_id" class="form-select">
                <option value="0">Alle</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?php if ($district_id === (int)$d['id']) echo 'selected'; ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-3">
            <label class="form-label">Stadtteil</label>
            <select name="neighborhood_id" class="form-select">
                <option value="0">Alle</option>
                <?php foreach ($neighborhoods as $n): ?>
                    <option value="<?= (int)$n['id'] ?>" <?php if ($neighborhood_id === (int)$n['id']) echo 'selected'; ?>>
                        <?= htmlspecialchars($n['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">Von</label>
            <input type="date" class="form-control" name="from" min="<?= htmlspecialchars($today) ?>" value="<?= htmlspecialchars($from) ?>">
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">Bis</label>
            <input type="date" class="form-control" name="to" min="<?= htmlspecialchars($today) ?>" value="<?= htmlspecialchars($to) ?>">
        </div>

        <div class="col-12 col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Suchen</button>
        </div>
    </div>
</form>

