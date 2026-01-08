<?php
$today = date('Y-m-d');

$district_id = isset($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
$district_q = $_GET['district_q'] ?? '';
$neighborhood_id = isset($_GET['neighborhood_id']) ? (int)$_GET['neighborhood_id'] : 0;
$neighborhood_q = $_GET['neighborhood_q'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Load districts & neighborhoods for selects (if $conn is available)
$districts = [];
$neighborhoods = [];
if (isset($conn)) {
    if (trim($district_q) !== '') {
    // force a compatible collation on both sides to avoid mixed-collation errors
    $ds = $conn->prepare("SELECT id, name FROM districts WHERE name COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%') COLLATE utf8mb4_general_ci ORDER BY name ASC");
        if ($ds) {
            $ds->bind_param('s', $district_q);
            $ds->execute();
            $dr = $ds->get_result();
            while ($d = $dr->fetch_assoc()) $districts[] = $d;
            $ds->close();
        }
    } else {
        $ds = $conn->prepare("SELECT id, name FROM districts ORDER BY name ASC");
        if ($ds) {
            $ds->execute();
            $dr = $ds->get_result();
            while ($d = $dr->fetch_assoc()) $districts[] = $d;
            $ds->close();
        }
    }

    // neighborhoods: allow optional name filter (neighborhood_q) and optional district filter
    if (trim($neighborhood_q) !== '') {
        if ($district_id > 0) {
            $ns = $conn->prepare("SELECT id, district_id, name FROM neighborhoods WHERE district_id = ? AND name COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%') COLLATE utf8mb4_general_ci ORDER BY name ASC");
            if ($ns) {
                $ns->bind_param('is', $district_id, $neighborhood_q);
                $ns->execute();
                $nr = $ns->get_result();
                while ($n = $nr->fetch_assoc()) $neighborhoods[] = $n;
                $ns->close();
            }
        } else {
            $ns = $conn->prepare("SELECT id, district_id, name FROM neighborhoods WHERE name COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%') COLLATE utf8mb4_general_ci ORDER BY name ASC");
            if ($ns) {
                $ns->bind_param('s', $neighborhood_q);
                $ns->execute();
                $nr = $ns->get_result();
                while ($n = $nr->fetch_assoc()) $neighborhoods[] = $n;
                $ns->close();
            }
        }
    } else {
        $ns = $conn->prepare("SELECT id, district_id, name FROM neighborhoods ORDER BY name ASC");
        if ($ns) {
            $ns->execute();
            $nr = $ns->get_result();
            while ($n = $nr->fetch_assoc()) $neighborhoods[] = $n;
            $ns->close();
        }
    }
}
?>

<form method="GET" action="search.php" class="row g-3 justify-content-center mt-4">
    <div class="col-md-3">
        <label class="form-label">Distrikt</label>
        <div class="input-group mb-2">
            <input type="text" name="district_q" class="form-control" placeholder="Distrikt suchen (Teilname)" value="<?= htmlspecialchars($district_q) ?>">
            <button class="btn btn-outline-secondary" type="submit" title="Filter">Filter</button>
        </div>
        <div class="d-flex">
            <select name="district_id" class="form-select me-2">
                <option value="0">-- Alle Distrikte --</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $district_id === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- no-JS fallback: visible button to refresh neighborhood list after choosing a district -->
            <button type="submit" name="refresh_neighborhoods" value="1" class="btn btn-outline-secondary" title="Stadtteile aktualisieren">Aktualisieren</button>
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Stadtteil</label>
        <div class="input-group mb-2">
            <input type="text" name="neighborhood_q" class="form-control" placeholder="Stadtteil suchen (Teilname)" value="<?= htmlspecialchars($neighborhood_q) ?>">
            <button class="btn btn-outline-secondary" type="submit" title="Filter">Filter</button>
        </div>
        <select name="neighborhood_id" class="form-select">
            <option value="0">-- Alle Stadtteile --</option>
            <?php foreach ($neighborhoods as $n): ?>
                <?php if ($district_id === 0 || $n['district_id'] == $district_id): ?>
                    <option value="<?= (int)$n['id'] ?>" <?= $neighborhood_id === (int)$n['id'] ? 'selected' : '' ?>><?= htmlspecialchars($n['name']) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
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

<!-- JavaScript removed: dependent selects are rendered server-side (no-JS) -->

<!-- No JavaScript: dependent selects are handled server-side via the "Aktualisieren" button. -->
