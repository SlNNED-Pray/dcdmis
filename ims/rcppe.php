<?php
require_once(__DIR__ . '/navigation.php');

$allRows = connection()->query(
    'SELECT * FROM rcppe_items ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$search = trim((string) ($_GET['q'] ?? ''));
$locationFilter = trim((string) ($_GET['location'] ?? ''));
$personFilter = trim((string) ($_GET['person'] ?? ''));

$locations = [];
$persons = [];
foreach ($allRows as $row) {
    $rowLocation = trim((string) $row['location']);
    $rowPerson = trim((string) $row['accountable_person']);
    if ($rowLocation !== '') {
        $locations[$rowLocation] = true;
    }
    if ($rowPerson !== '') {
        $persons[$rowPerson] = true;
    }
}
$locations = array_keys($locations);
$persons = array_keys($persons);
usort($locations, 'strnatcasecmp');
usort($persons, 'strnatcasecmp');

$rows = array_values(array_filter($allRows, static function (array $row) use ($search, $locationFilter, $personFilter): bool {
    if ($locationFilter !== '' && (string) $row['location'] !== $locationFilter) {
        return false;
    }
    if ($personFilter !== '' && (string) $row['accountable_person'] !== $personFilter) {
        return false;
    }
    if ($search !== '') {
        $haystack = implode(' ', [
            $row['article'],
            $row['description'],
            $row['property_number'],
            $row['location'],
            $row['accountable_person'],
            $row['remarks'],
        ]);
        if (mb_stripos($haystack, $search) === false) {
            return false;
        }
    }

    return true;
}));

$totalRecords = count($rows);
$totalValue = 0.0;
$totalBal = 0;
$totalOnHand = 0;
$totalShortage = 0;
foreach ($rows as $row) {
    $totalValue += (float) $row['unit_value'];
    $totalBal += (int) $row['bal_per_card'];
    $totalOnHand += (int) $row['on_hand_count'];
    $totalShortage += (int) $row['shortage'];
}

$hasFilter = $search !== '' || $locationFilter !== '' || $personFilter !== '';

messageAlert($showAlert ?? false, $message ?? '', $success ?? true);
contentTitle('RCPPE');
imsNav('rcppe');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
	<div>
		<button class="btn btn-secondary btn-sm" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
	</div>
	<div class="text-muted small">
		Showing <strong><?= number_format($totalRecords) ?></strong> of <strong><?= number_format(count($allRows)) ?></strong> record(s)
		<?php if ($locationFilter !== ''): ?> · <?= e($locationFilter) ?><?php endif; ?>
		<?php if ($personFilter !== ''): ?> · <?= e($personFilter) ?><?php endif; ?>
		<?php if ($search !== ''): ?> · matching “<?= e($search) ?>”<?php endif; ?>
	</div>
</div>

<form method="get" class="card shadow mb-4"><?= imsEmbedInput() ?>
	<input type="hidden" name="v" value="<?= e(cipher('RCPPE')) ?>">
	<div class="card-body pb-2">
		<div class="form-row align-items-end">
			<div class="form-group col-lg-4 mb-2">
				<label class="small mb-1" for="rcppe-search">Search</label>
				<div class="input-group input-group-sm">
					<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
					<input class="form-control" type="search" id="rcppe-search" name="q" value="<?= e($search) ?>" placeholder="Article, description, location, accountable person…">
				</div>
			</div>
			<div class="form-group col-lg-3 mb-2">
				<label class="small mb-1" for="rcppe-location">Location</label>
				<select class="form-control form-control-sm" id="rcppe-location" name="location">
					<option value="">All locations</option>
					<?php foreach ($locations as $location): ?>
						<option value="<?= e($location) ?>"<?= $location === $locationFilter ? ' selected' : '' ?>><?= e($location) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group col-lg-3 mb-2">
				<label class="small mb-1" for="rcppe-person">Accountable Person</label>
				<select class="form-control form-control-sm" id="rcppe-person" name="person">
					<option value="">All accountable persons</option>
					<?php foreach ($persons as $person): ?>
						<option value="<?= e($person) ?>"<?= $person === $personFilter ? ' selected' : '' ?>><?= e($person) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group col-lg-2 mb-2">
				<button class="btn btn-primary btn-sm btn-block" type="submit"><i class="fas fa-filter"></i> Apply</button>
				<?php if ($hasFilter): ?>
					<a class="btn btn-outline-secondary btn-sm btn-block" href="<?= imsUri('RCPPE') ?>"><i class="fas fa-times"></i> Clear</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</form>

<div class="row">
	<?php
	$stats = [
		['Records', number_format($totalRecords), 'fa-file-alt', 'primary'],
		['Total Unit Value', '&#8369;' . number_format($totalValue, 2), 'fa-coins', 'success'],
		['On Hand Count', number_format($totalOnHand), 'fa-cubes', 'info'],
		['Shortage', number_format($totalShortage), 'fa-exclamation-triangle', $totalShortage > 0 ? 'danger' : 'secondary'],
	];
	foreach ($stats as [$label, $value, $icon, $color]): ?>
		<div class="col-xl-3 col-md-6 mb-3">
			<div class="card border-left-<?= $color ?> shadow h-100 py-2">
				<div class="card-body py-2">
					<div class="row no-gutters align-items-center">
						<div class="col mr-2">
							<div class="text-xs font-weight-bold text-<?= $color ?> text-uppercase mb-1"><?= e($label) ?></div>
							<div class="h6 mb-0 font-weight-bold text-gray-800"><?= $value ?></div>
						</div>
						<div class="col-auto"><i class="fas <?= $icon ?> fa-2x text-gray-300"></i></div>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="card shadow mb-4">
	<div class="card-header"><strong>Report on the Physical Count of Property, Plant and Equipment</strong></div>
	<div class="card-body">
		<?php if (!$rows): ?>
			<p class="text-center text-muted my-4 mb-0">No records match the selected filters.</p>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-sm table-hover table-bordered" id="rcppe-table">
				<thead class="thead-light">
					<tr>
						<th>Article</th>
						<th>Description</th>
						<th>Property Number</th>
						<th>Unit</th>
						<th class="text-right">Unit Value</th>
						<th class="text-right">Bal. Per Card (Qty)</th>
						<th class="text-right">On Hand Count (Qty)</th>
						<th class="text-right">Shortage (Qty.)</th>
						<th>LOCATION</th>
						<th>Accountable Person (Remarks, State whereabouts, conditions, etc.)</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row): ?>
					<tr>
						<td><?= e($row['article']) ?></td>
						<td><?= e($row['description']) ?></td>
						<td class="text-nowrap"><?= $row['property_number'] !== '' ? e($row['property_number']) : '<span class="text-muted">&mdash;</span>' ?></td>
						<td><?= e($row['unit']) ?></td>
						<td class="text-right text-nowrap">&#8369;<?= number_format((float) $row['unit_value'], 2) ?></td>
						<td class="text-right"><?= number_format((int) $row['bal_per_card']) ?></td>
						<td class="text-right"><?= number_format((int) $row['on_hand_count']) ?></td>
						<td class="text-right"><?= number_format((int) $row['shortage']) ?></td>
						<td class="text-nowrap"><?= e($row['location']) ?></td>
						<td>
							<?= e($row['accountable_person']) ?>
							<?php if ($row['remarks'] !== ''): ?>
								<div class="small text-muted"><?= e($row['remarks']) ?></div>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot class="thead-light font-weight-bold">
					<tr>
						<td colspan="4" class="text-right">Total</td>
						<td class="text-right text-nowrap">&#8369;<?= number_format($totalValue, 2) ?></td>
						<td class="text-right"><?= number_format($totalBal) ?></td>
						<td class="text-right"><?= number_format($totalOnHand) ?></td>
						<td class="text-right"><?= number_format($totalShortage) ?></td>
						<td colspan="2"></td>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php endif; ?>
	</div>
</div>

<style>
@media print {
	.btn, .nav-tabs, .sidebar, #accordionSidebar, #scroll-to-top, .navbar, footer, form, .card.border-left-primary, .card.border-left-success, .card.border-left-info, .card.border-left-danger, .card.border-left-secondary { display: none !important; }
	#content-wrapper, #content, .container-fluid { margin: 0 !important; padding: 0 !important; width: 100% !important; }
	.card { border: 0 !important; box-shadow: none !important; }
	.card-header { text-align: center; border: 0; font-size: 13pt; }
	.table { font-size: 8pt; }
	.table-responsive { overflow: visible !important; }
	thead { display: table-header-group; }
	tr { page-break-inside: avoid; }
}
</style>
