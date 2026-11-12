<?php
require_once(__DIR__ . '/navigation.php');
require_once(root() . '/ims/helpers.php');

$groupOptions = query("SELECT DISTINCT item_group FROM items WHERE item_group IS NOT NULL AND item_group <> '' ORDER BY item_group ASC");

$stockNoOptions = query("SELECT DISTINCT stock_no FROM items WHERE stock_no IS NOT NULL AND stock_no <> '' ORDER BY stock_no ASC");
$descriptionOptions = query("SELECT DISTINCT description FROM items WHERE description IS NOT NULL AND description <> '' ORDER BY description ASC");
$officeOptions = query(
    "SELECT name FROM sections WHERE name IS NOT NULL AND name <> ''
     UNION
     SELECT name FROM schools WHERE name IS NOT NULL AND name <> ''
     ORDER BY name ASC"
);
$employeeOptions = array_map(static function (array $employee): array {
    $office = trim((string) ($employee['section_name'] ?? ''));
    if ($office === '') {
        $office = trim((string) ($employee['school_name'] ?? ''));
    }
    return ['name' => (string) $employee['name'], 'office' => $office];
}, query(
    "SELECT e.id,
            CONCAT(e.first_name, ' ', e.last_name) AS name,
            s.name AS section_name,
            sc.name AS school_name
     FROM employees e
     LEFT JOIN (
         SELECT sa2.employee_id, MIN(sa2.station_id) AS station_id
         FROM station_assignments sa2
         INNER JOIN (
             SELECT employee_id, MAX(assignment_date) AS assignment_date
             FROM station_assignments
             GROUP BY employee_id
         ) latest ON latest.employee_id = sa2.employee_id AND latest.assignment_date = sa2.assignment_date
         GROUP BY sa2.employee_id
     ) sa ON sa.employee_id = e.id
     LEFT JOIN sections s ON s.id = sa.station_id
     LEFT JOIN schools sc ON sc.id = sa.station_id
     ORDER BY e.last_name, e.first_name"
));

$personnelValues = [];
foreach ($employeeOptions as $option) {
    $personnelValues[strtolower($option['name'])] = $option;
}
foreach (query("SELECT DISTINCT personnel FROM items WHERE personnel IS NOT NULL AND personnel <> '' ORDER BY personnel ASC") ?: [] as $row) {
    $name = trim((string) $row['personnel']);
    if ($name !== '' && !isset($personnelValues[strtolower($name)])) {
        $personnelValues[strtolower($name)] = ['name' => $name, 'office' => ''];
    }
}
$personnelValues = array_values($personnelValues);
usort($personnelValues, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

$officeValues = [];
foreach ($officeOptions ?: [] as $row) {
    $officeValues[strtolower((string) $row['name'])] = (string) $row['name'];
}
foreach (query("SELECT DISTINCT office FROM items WHERE office IS NOT NULL AND office <> '' ORDER BY office ASC") ?: [] as $row) {
    $name = trim((string) $row['office']);
    if ($name !== '') {
        $officeValues[strtolower($name)] = $name;
    }
}
ksort($officeValues);

$autofillJson = json_encode([
    'stock_no' => array_map(static fn (array $row): string => (string) $row['stock_no'], $stockNoOptions ?: []),
    'description' => array_map(static fn (array $row): string => (string) $row['description'], $descriptionOptions ?: []),
    'item_group' => array_map(static fn (array $row): string => (string) $row['item_group'], $groupOptions ?: []),
    'personnel' => $personnelValues,
    'office' => array_values($officeValues),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$item = [
    'stock_no' => trim($_POST['stock_no'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'item_group' => trim($_POST['item_group'] ?? ''),
    'unit' => trim($_POST['unit'] ?? ''),
    'total_units' => (int) ($_POST['total_units'] ?? 0),
    'quantity' => (int) ($_POST['quantity'] ?? 0),
    'min_qty' => (int) ($_POST['min_qty'] ?? 0),
    'unit_cost' => (float) ($_POST['unit_cost'] ?? 0),
    'personnel' => trim($_POST['personnel'] ?? ''),
    'office' => trim($_POST['office'] ?? ''),
    'item_status' => in_array($submittedStatus = trim($_POST['item_status'] ?? ''), ['Functional', 'Transferred'], true) ? $submittedStatus : 'Functional',
    'transfer_to' => trim($_POST['transfer_to'] ?? ''),
    'remarks' => trim($_POST['remarks'] ?? ''),
];

$openModal = isset($_POST['save-item']) || $url === 'Create Stock Item';
?>
<style>
.ims-autofill-wrap { position: relative; }
.ims-autofill-list { display: none; left: 0; right: 0; top: 100%; position: absolute; max-height: 220px; overflow: auto; z-index: 1000; }
.ims-autofill-list .dropdown-item { white-space: normal; }
.ims-autofill-list .ims-autofill-sub { display: block; font-size: .75rem; color: #6c757d; }
</style>
<div class="modal fade" id="itemModal" tabindex="-1" role="dialog" aria-labelledby="itemModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<form method="post">
				<?= csrf_field(); ?>
				<div class="modal-header">
					<h5 class="modal-title" id="itemModalLabel"><i class="fas fa-boxes"></i> New stock item</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="form-row">
						<div class="form-group col-md-6 ims-autofill-wrap"><label>Stock No. <span class="text-danger">*</span></label><input class="form-control ims-autofill" name="stock_no" required autocomplete="off" data-suggestions="stock_no" value="<?= e($item['stock_no']) ?>"><ul class="ims-autofill-list dropdown-menu"></ul></div>
						<div class="form-group col-md-6"><label>Unit <span class="text-danger">*</span></label>
							<select class="form-control" name="unit" required>
								<option value="">Select unit</option>
								<?php foreach (['bottle','gallon','piece','unit','pack','can','pouch','bundle','roll','tube','pad','box','ream','book','cart','license','ticket','lot','set'] as $u): ?>
									<option value="<?= e($u) ?>" <?= strtolower($item['unit']) === $u ? 'selected' : '' ?>><?= e(ucfirst($u)) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="form-group ims-autofill-wrap"><label>Description <span class="text-danger">*</span></label><input class="form-control ims-autofill" name="description" required autocomplete="off" data-suggestions="description" value="<?= e($item['description']) ?>"><ul class="ims-autofill-list dropdown-menu"></ul></div>
					<div class="form-group ims-autofill-wrap">
						<label>Group / Category</label>
						<input class="form-control ims-autofill" name="item_group" autocomplete="off" data-suggestions="item_group" value="<?= e($item['item_group']) ?>" placeholder="Select or type a group">
						<ul class="ims-autofill-list dropdown-menu"></ul>
					</div>
					<div class="form-row">
						<div class="form-group col-md-3"><label>Total units</label><input class="form-control" type="number" min="0" name="total_units" value="<?= $item['total_units'] ?: '' ?>"></div>
						<div class="form-group col-md-3"><label>Unit cost</label><input class="form-control" type="number" min="0" step="0.01" name="unit_cost" value="<?= e($item['unit_cost']) ?>"></div>
					</div>
					<div class="form-row">
						<div class="form-group col-md-4 ims-autofill-wrap"><label>Personnel / Custodian</label><input class="form-control ims-autofill" name="personnel" autocomplete="off" data-suggestions="personnel" value="<?= e($item['personnel']) ?>"><ul class="ims-autofill-list dropdown-menu"></ul></div>
						<div class="form-group col-md-4 ims-autofill-wrap"><label>Office</label><input class="form-control ims-autofill" name="office" autocomplete="off" data-suggestions="office" value="<?= e($item['office']) ?>"><ul class="ims-autofill-list dropdown-menu"></ul></div>
						<div class="form-group col-md-4"><label>Status</label>
							<select class="form-control" name="item_status" id="item-status">
								<option value="Functional" <?= $item['item_status'] === 'Functional' ? 'selected' : '' ?>>Functional</option>
								<option value="Transferred" <?= $item['item_status'] === 'Transferred' ? 'selected' : '' ?>>Transferred</option>
							</select>
						</div>
					</div>
					<div class="form-row" id="transfer-row" style="<?= $item['item_status'] === 'Transferred' ? '' : 'display:none;' ?>">
						<div class="form-group col-md-6"><label>Transferred to (personnel) <span class="text-danger">*</span></label><input class="form-control" name="transfer_to" id="transfer-to" value="<?= e($item['transfer_to']) ?>"></div>
					</div>
					<div class="form-row">
						<div class="form-group col-md-6"><label>Opening quantity</label><input class="form-control" type="number" min="0" name="quantity" value="<?= $item['quantity'] ?>"></div>
						<div class="form-group col-md-6"><label>Minimum quantity</label><input class="form-control" type="number" min="0" name="min_qty" value="<?= $item['min_qty'] ?>"></div>
					</div>
					<div class="form-group"><label>Remarks</label><textarea class="form-control" name="remarks" rows="2"><?= e($item['remarks']) ?></textarea></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button class="btn btn-primary" type="submit" name="save-item"><i class="fas fa-save"></i> Save item</button>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
(function () {
    var sel = document.getElementById('item-status');
    var row = document.getElementById('transfer-row');
    var inp = document.getElementById('transfer-to');
    function toggle() {
        var on = sel && sel.value === 'Transferred';
        if (row) { row.style.display = on ? '' : 'none'; }
        if (inp) { inp.required = on; }
    }
    if (sel) { sel.addEventListener('change', toggle); toggle(); }
})();
</script>
<script>
(function () {
    var IMS_AUTOFILL = <?= $autofillJson ?: '{}' ?>;
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    document.querySelectorAll('.ims-autofill-wrap').forEach(function (wrap) {
        var input = wrap.querySelector('.ims-autofill');
        var list = wrap.querySelector('.ims-autofill-list');
        if (!input || !list) return;
        var key = input.getAttribute('data-suggestions');
        var source = IMS_AUTOFILL[key] || [];

        function hide() { list.style.display = 'none'; }

        function show() {
            var q = input.value.trim().toLowerCase();
            if (!q) { hide(); return; }
            var results = source.filter(function (entry) {
                var text = typeof entry === 'string' ? entry : (entry.name || '');
                return text.toLowerCase().indexOf(q) !== -1;
            }).slice(0, 20);
            if (!results.length) { hide(); return; }
            list.innerHTML = '';
            results.forEach(function (entry) {
                var text = typeof entry === 'string' ? entry : entry.name;
                var sub = (typeof entry === 'object' && entry.office) ? entry.office : '';
                var li = document.createElement('li');
                li.className = 'dropdown-item';
                li.setAttribute('role', 'option');
                li.innerHTML = esc(text) + (sub ? '<span class="ims-autofill-sub">' + esc(sub) + '</span>' : '');
                li.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    input.value = text;
                    if (key === 'personnel' && sub) {
                        var officeInput = document.querySelector('#itemModal [name="office"]');
                        if (officeInput) officeInput.value = sub;
                    }
                    hide();
                });
                list.appendChild(li);
            });
            list.style.display = 'block';
        }

        input.addEventListener('input', show);
        input.addEventListener('focus', function () { if (input.value.trim() !== '') show(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') hide();
            if (e.key === 'Enter' && list.style.display === 'block' && list.children.length > 0) {
                e.preventDefault();
                list.children[0].dispatchEvent(new MouseEvent('mousedown'));
            }
        });
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) hide();
        });
    });
})();
</script>
<?php if ($openModal): ?>
<script>document.addEventListener('DOMContentLoaded', function () { if (window.jQuery && jQuery.fn.modal) { jQuery('#itemModal').modal('show'); } });</script>
<?php endif; ?>