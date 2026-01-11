<?php
/**
 * Calendar Component - Display and select booking dates
 * Uses validators from validation.php: is_valid_date(), is_valid_date_range()
 */

// Get URL parameters
$parkingId = $_GET['id'] ?? 0;
$selectedStart = $_GET['start_date'] ?? null;
$selectedEnd = $_GET['end_date'] ?? null;
$currentMonth = (int)($_GET['month'] ?? date('m'));
$currentYear = (int)($_GET['year'] ?? date('Y'));

// Sanitize dates
$today = date('Y-m-d');
if ($selectedStart && !is_valid_date($selectedStart)) {
    $selectedStart = null;
}
if ($selectedEnd && !is_valid_date($selectedEnd)) {
    $selectedEnd = null;
}

// Validate date range
$rangeError = '';
if ($selectedStart && $selectedEnd) {
    $rangeError = is_valid_date_range($selectedStart, $selectedEnd);
    if ($rangeError) {
        $selectedStart = null;
        $selectedEnd = null;
    }
}

// Get availability
$availableDates = get_available_dates($parkingId);
$bookedDates = get_booked_dates($parkingId);

// Calendar setup
$currentDate = new DateTime("$currentYear-$currentMonth-01");
$daysInMonth = (int)$currentDate->format('t');
$firstDayOfWeek = (int)$currentDate->format('N');
$previousMonth = (clone $currentDate)->modify('-1 month');
$nextMonth = (clone $currentDate)->modify('+1 month');
?>

<h5 class="mb-3">Select Dates</h5>

<?php if ($rangeError): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($rangeError) ?>
    </div>
<?php endif; ?>

<?php if ($selectedStart): ?>
    <div class="alert alert-info mb-3">
        <strong>Selected:</strong> <?= htmlspecialchars($selectedStart) ?><?= $selectedEnd ? ' → ' . htmlspecialchars($selectedEnd) : ' (select end date)' ?>
        <a href="?id=<?= $parkingId ?>" class="btn btn-sm btn-outline-secondary float-end">Clear</a>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <!-- Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="?id=<?= $parkingId ?>&month=<?= $previousMonth->format('m') ?>&year=<?= $previousMonth->format('Y') ?><?= $selectedStart ? '&start_date=' . htmlspecialchars($selectedStart) : '' ?><?= $selectedEnd ? '&end_date=' . htmlspecialchars($selectedEnd) : '' ?>"
               class="btn btn-sm btn-outline-secondary">←</a>
            <h6 class="mb-0">
                <?= $currentDate->format('F Y') ?>
            </h6>
            <a href="?id=<?= $parkingId ?>&month=<?= $nextMonth->format('m') ?>&year=<?= $nextMonth->format('Y') ?><?= $selectedStart ? '&start_date=' . htmlspecialchars($selectedStart) : '' ?><?= $selectedEnd ? '&end_date=' . htmlspecialchars($selectedEnd) : '' ?>"
               class="btn btn-sm btn-outline-secondary">→</a>
        </div>

        <!-- Calendar Table -->
        <table class="table table-sm text-center mb-0">
            <thead>
                <tr>
                    <th class="small">Mo</th>
                    <th class="small">Tu</th>
                    <th class="small">We</th>
                    <th class="small">Th</th>
                    <th class="small">Fr</th>
                    <th class="small">Sa</th>
                    <th class="small">Su</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <!-- Empty cells before month -->
                    <?php for ($i = 1; $i < $firstDayOfWeek; $i++): ?>
                        <td></td>
                    <?php endfor; ?>

                    <!-- Day cells -->
                    <?php
                    $cellCount = $firstDayOfWeek;
                    for ($day = 1; $day <= $daysInMonth; $day++, $cellCount++):
                        $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                        $isPast = $dateStr < $today;
                        $isAvailable = isset($availableDates[$dateStr]);
                        $isBooked = isset($bookedDates[$dateStr]);

                        // Determine badge color
                        if ($isPast) {
                            $badgeColor = 'bg-secondary';
                            $isClickable = false;
                        } elseif ($isBooked) {
                            $badgeColor = 'bg-danger';
                            $isClickable = false;
                        } elseif ($isAvailable) {
                            $badgeColor = 'bg-success';
                            $isClickable = true;
                        } else {
                            $badgeColor = 'bg-light text-dark';
                            $isClickable = false;
                        }

                        // Build click URL
                        if ($isClickable) {
                            $url = !$selectedStart
                                ? "?id=$parkingId&month=$currentMonth&year=$currentYear&start_date=$dateStr"
                                : "?id=$parkingId&month=$currentMonth&year=$currentYear&start_date=$selectedStart&end_date=$dateStr";
                        }
                    ?>
                        <td style="padding: 4px;">
                            <?php if ($isClickable): ?>
                                <a href="<?= htmlspecialchars($url) ?>"
                                   class="badge <?= $badgeColor ?>"
                                   style="text-decoration: none; cursor: pointer;">
                                    <?= $day ?>
                                </a>
                            <?php else: ?>
                                <span class="badge <?= $badgeColor ?>">
                                    <?= $day ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <?php if ($cellCount % 7 === 0 && $day < $daysInMonth): ?>
                            </tr><tr>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Empty cells after month -->
                    <?php while ($cellCount % 7 !== 1): ?>
                        <td></td>
                        <?php $cellCount++; ?>
                    <?php endwhile; ?>
                </tr>
            </tbody>
        </table>

        <!-- Legend -->
        <div class="small text-muted mt-2">
            <span><span class="badge bg-success">●</span> Available</span>
            <span class="ms-3"><span class="badge bg-danger">●</span> Booked</span>
            <span class="ms-3"><span class="badge bg-secondary">●</span> Past</span>
            <span class="ms-3"><span class="badge bg-light text-dark">●</span> Unavailable</span>
        </div>
    </div>
</div>

<!-- Booking Form -->
<?php if ($selectedStart && $selectedEnd): ?>
    <form method="POST" action="book.php" class="mb-3">
        <input type="hidden" name="parking_id" value="<?= $parkingId ?>">
        <input type="hidden" name="booking_start" value="<?= htmlspecialchars($selectedStart) ?>">
        <input type="hidden" name="booking_end" value="<?= htmlspecialchars($selectedEnd) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <button type="submit" class="btn btn-primary w-100">Book now</button>
    </form>
<?php else: ?>
    <p class="text-muted text-center">Select start and end date in the calendar to book.</p>
<?php endif; ?>

