<?php
// dipee/views/import.php
require_once __DIR__ . '/../lib/dbf.php';

$path = trim((string) ($_POST['path'] ?? LEGACY_PATH));
$report = null;
$sample = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $mode = $_POST['mode'] ?? 'preview';
    $targets = [
        'stations' => isset($_POST['stations']),
        'types' => isset($_POST['types']),
        'employees' => isset($_POST['employees']),
        'deductions' => isset($_POST['deductions']),
    ];

    try {
        if (!is_dir($path)) {
            throw new RuntimeException('Folder not found: ' . $path);
        }
        $required = ['MASTFILE.DBF'];
        foreach ($required as $file) {
            if (!file_exists($path . '/' . $file)) {
                throw new RuntimeException('Missing ' . $file . ' in ' . $path);
            }
        }

        $report = ['mode' => $mode, 'tables' => []];

        // Stations (STNTABLE) -------------------------------------------------
        if ($targets['stations'] && file_exists($path . '/STNTABLE.DBF')) {
            $table = new LegacyDbf($path . '/STNTABLE.DBF');
            $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
            foreach ($table->rows() as $row) {
                $code = trim((string) ($row['STACODE'] ?? ''));
                $name = trim((string) ($row['STANAMEL'] ?? '')) ?: trim((string) ($row['STANAME'] ?? ''));
                if ($code === '' || $name === '') {
                    $counts['skipped']++;
                    continue;
                }
                if ($mode === 'import') {
                    if (find('SELECT `code` FROM `stations` WHERE `code` = ?', [$code])) {
                        update('stations', ['name' => $name], '`code` = ?', [$code]);
                        $counts['updated']++;
                    } else {
                        insert('stations', ['code' => $code, 'name' => $name]);
                        $counts['inserted']++;
                    }
                } else {
                    $counts['inserted']++;
                }
            }
            $report['tables']['STNTABLE'] = $counts;
        }

        // Deduction types (DEDTABLE) ------------------------------------------
        $typeMap = [];
        if (file_exists($path . '/DEDTABLE.DBF')) {
            $table = new LegacyDbf($path . '/DEDTABLE.DBF');
            $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
            foreach ($table->rows() as $row) {
                $code = trim((string) ($row['DEDCODE'] ?? ''));
                $name = trim((string) ($row['DEDNAME'] ?? '')) ?: trim((string) ($row['DEDNAMEL'] ?? ''));
                if ($code === '' || $name === '') {
                    $counts['skipped']++;
                    continue;
                }
                $kind = strtoupper(trim((string) ($row['TYPE'] ?? ''))) === 'L' ? 'loan' : (strtoupper(trim((string) ($row['TYPE'] ?? ''))) === 'P' ? 'premium' : 'other');
                $agency = trim((string) ($row['AGENCY'] ?? '')) ?: null;

                if ($targets['types'] && $mode === 'import') {
                    $existing = find('SELECT `id` FROM `deduction_types` WHERE `code` = ?', [$code]);
                    if ($existing) {
                        update('deduction_types', ['name' => $name, 'kind' => $kind, 'agency' => $agency], '`id` = ?', [(int) $existing['id']]);
                        $typeMap[$code] = (int) $existing['id'];
                        $counts['updated']++;
                    } else {
                        $typeMap[$code] = (int) insert('deduction_types', [
                            'code' => $code, 'name' => $name, 'kind' => $kind, 'agency' => $agency, 'active' => 1,
                        ]);
                        $counts['inserted']++;
                    }
                } else {
                    $counts['inserted']++;
                }
            }
            $report['tables']['DEDTABLE'] = $counts;
        }

        // Employees (MASTFILE) -------------------------------------------------
        if ($targets['employees']) {
            $table = new LegacyDbf($path . '/MASTFILE.DBF');
            $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
            $preview = [];
            foreach ($table->rows() as $row) {
                $empno = trim((string) ($row['EMPNO'] ?? ''));
                $last = trim((string) ($row['LNAME'] ?? ''));
                $first = trim((string) ($row['FNAME'] ?? ''));
                if ($empno === '' || $last === '') {
                    $counts['skipped']++;
                    continue;
                }
                $data = [
                    'empno' => $empno,
                    'last_name' => $last,
                    'first_name' => $first,
                    'middle_name' => trim((string) ($row['MI'] ?? '')) ?: null,
                    'station_code' => trim((string) ($row['STACODE'] ?? '')) ?: null,
                    'position' => trim((string) ($row['POSCODE'] ?? '')) ?: null,
                    'grade' => trim((string) ($row['GRADE'] ?? '')) ?: null,
                    'step' => trim((string) ($row['STEP'] ?? '')) ?: null,
                    'basic_pay' => (float) ($row['BASIC'] ?? 0),
                    'pera' => (float) ($row['PERA'] ?? 0),
                    'trans_allow' => (float) ($row['TRANS'] ?? 0),
                    'aca' => (float) ($row['ACA'] ?? 0),
                    'rata' => (float) ($row['RATA'] ?? 0),
                    'ontop' => (float) ($row['ONTOP'] ?? 0),
                    'long_pay' => (float) ($row['LONG'] ?? 0),
                    'taxcode' => trim((string) ($row['TAXCODE'] ?? '')) ?: null,
                    'status' => trim((string) ($row['STATUS'] ?? 'P')) ?: 'P',
                    'paymode' => trim((string) ($row['PAYMODE'] ?? 'A')) ?: 'A',
                    'account_no' => trim((string) ($row['ACCOUNT'] ?? '')) ?: null,
                    'gsis_pn' => trim((string) ($row['GSIS_PN'] ?? '')) ?: null,
                    'phic_id' => trim((string) ($row['PHIC_ID'] ?? '')) ?: null,
                    'hdmf_id' => trim((string) ($row['HDMF_ID'] ?? '')) ?: null,
                    'tin' => trim((string) ($row['TIN'] ?? '')) ?: null,
                    'hire_date' => $row['HIREDATE'] ?: null,
                ];
                if ($mode === 'import') {
                    $existing = find('SELECT `id` FROM `employees` WHERE `empno` = ?', [$empno]);
                    if ($existing) {
                        update('employees', $data + ['updated_at' => date('Y-m-d H:i:s')], '`id` = ?', [(int) $existing['id']]);
                        $counts['updated']++;
                    } else {
                        insert('employees', $data + ['created_at' => date('Y-m-d H:i:s')]);
                        $counts['inserted']++;
                    }
                } else {
                    $counts['inserted']++;
                    if (count($preview) < 5) {
                        $preview[] = $data;
                    }
                }
            }
            $report['tables']['MASTFILE'] = $counts;
            $sample = $preview;
        }

        // Employee deductions (DEDFILE) ---------------------------------------
        if ($targets['deductions'] && file_exists($path . '/DEDFILE.DBF')) {
            $table = new LegacyDbf($path . '/DEDFILE.DBF');
            $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
            $employeesByNo = [];
            foreach (query('SELECT `id`, `empno` FROM `employees`') ?: [] as $row) {
                $employeesByNo[$row['empno']] = (int) $row['id'];
            }
            foreach ($table->rows() as $row) {
                $empno = trim((string) ($row['EMPNO'] ?? ''));
                $code = trim((string) ($row['DEDCODE'] ?? ''));
                $amount = (float) ($row['DEDAMT'] ?? 0);
                $status = strtoupper(trim((string) ($row['STATUS'] ?? 'A')));
                if ($empno === '' || $code === '' || $amount <= 0 || $status !== 'A') {
                    $counts['skipped']++;
                    continue;
                }
                if ($mode !== 'import') {
                    // A preview only checks that MASTFILE is selected, because the
                    // employees may not exist in the payroll database yet.
                    $counts[isset($_POST['employees']) ? 'inserted' : 'skipped']++;
                    continue;
                }
                if (!isset($employeesByNo[$empno])) {
                    $counts['skipped']++;
                    continue;
                }
                if (!isset($typeMap[$code])) {
                    $type = find('SELECT `id` FROM `deduction_types` WHERE `code` = ?', [$code]);
                    if (!$type) {
                        $counts['skipped']++;
                        continue;
                    }
                    $typeMap[$code] = (int) $type['id'];
                }
                $effFrom = null;
                if (!empty($row['EFFYY']) && !empty($row['EFFMM'])) {
                    $effFrom = sprintf('%04d-%02d-01', (int) $row['EFFYY'], (int) $row['EFFMM']);
                }
                $effTo = null;
                if (!empty($row['TERYY']) && !empty($row['TERMM'])) {
                    $effTo = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', (int) $row['TERYY'], (int) $row['TERMM'])));
                }
                $policy = trim((string) ($row['POLICYNO'] ?? '')) ?: null;
                $duplicate = find(
                    'SELECT `id` FROM `employee_deductions`
                     WHERE `employee_id` = ? AND `deduction_type_id` = ? AND `amount` = ?
                       AND COALESCE(`policy_no`, \'\') = ? LIMIT 1',
                    [$employeesByNo[$empno], $typeMap[$code], $amount, $policy ?? '']
                );
                if ($duplicate) {
                    $counts['skipped']++;
                    continue;
                }
                insert('employee_deductions', [
                    'employee_id' => $employeesByNo[$empno],
                    'deduction_type_id' => $typeMap[$code],
                    'amount' => $amount,
                    'policy_no' => $policy,
                    'effective_from' => $effFrom,
                    'effective_to' => $effTo,
                    'priority' => max(1, (int) ($row['P'] ?? 1)),
                    'status' => 'A',
                ]);
                $counts['inserted']++;
            }
            $report['tables']['DEDFILE'] = $counts;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<div class="page-head">
    <div>
        <h1>Import legacy data</h1>
        <p class="muted">Read-only migration from the FoxPro tables in <?= e(LEGACY_PATH) ?></p>
    </div>
</div>

<div class="grid-sidebar">
    <section class="card">
        <h2>What is imported</h2>
        <table class="table table-compact">
            <thead><tr><th>Legacy table</th><th>Target</th><th>Fields used</th></tr></thead>
            <tbody>
                <tr><td class="mono">STNTABLE.DBF</td><td>stations</td><td>STACODE, STANAMEL / STANAME</td></tr>
                <tr><td class="mono">DEDTABLE.DBF</td><td>deduction types</td><td>DEDCODE, DEDNAME, TYPE, AGENCY</td></tr>
                <tr><td class="mono">MASTFILE.DBF</td><td>employees</td><td>EMPNO, names, STACODE, POSCODE, GRADE, STEP, BASIC, PERA, TRANS, ACA, RATA, ONTOP, LONG, TAXCODE, STATUS, PAYMODE, government IDs</td></tr>
                <tr><td class="mono">DEDFILE.DBF</td><td>employee loans</td><td>only rows with STATUS = A and DEDAMT &gt; 0</td></tr>
            </tbody>
        </table>
        <p class="muted small">The legacy folder is never written to. Rows are keyed by employee number,
            deduction code and policy number, so re-running the import updates instead of duplicating.</p>
    </section>

    <section class="card">
        <h2>Run an import</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <label>
                Legacy folder
                <input type="text" name="path" value="<?= e($path) ?>" required>
            </label>
            <fieldset class="checks">
                <legend>Tables</legend>
                <label class="checkbox"><input type="checkbox" name="stations" value="1" checked> Stations</label>
                <label class="checkbox"><input type="checkbox" name="types" value="1" checked> Deduction types</label>
                <label class="checkbox"><input type="checkbox" name="employees" value="1" checked> Employees (MASTFILE)</label>
                <label class="checkbox"><input type="checkbox" name="deductions" value="1" checked> Employee loans (DEDFILE)</label>
            </fieldset>
            <div class="form-actions">
                <button class="btn btn-ghost" type="submit" name="mode" value="preview">Preview</button>
                <button class="btn btn-primary" type="submit" name="mode" value="import"
                        onclick="return confirm('Import the legacy data into the payroll database?');">Import now</button>
            </div>
        </form>
    </section>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($report): ?>
    <section class="card">
        <h2><?= $report['mode'] === 'import' ? 'Import result' : 'Preview' ?></h2>
        <?php if ($report['mode'] !== 'import'): ?>
            <p class="muted">Preview counts the rows that <em>would</em> be written. Nothing has changed yet.</p>
        <?php endif; ?>
        <table class="table table-compact">
            <thead><tr><th>Table</th><th class="num">New</th><th class="num">Updated</th><th class="num">Skipped</th></tr></thead>
            <tbody>
            <?php foreach ($report['tables'] as $name => $counts): ?>
                <tr>
                    <td class="mono"><?= e($name) ?></td>
                    <td class="num"><?= e(number_format($counts['inserted'])) ?></td>
                    <td class="num"><?= e(number_format($counts['updated'])) ?></td>
                    <td class="num"><?= e(number_format($counts['skipped'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($sample !== []): ?>
            <h3>Sample employees</h3>
            <table class="table table-compact">
                <thead><tr><th>Emp. no.</th><th>Name</th><th>Station</th><th class="num">Basic</th></tr></thead>
                <tbody>
                <?php foreach ($sample as $row): ?>
                    <tr>
                        <td class="mono"><?= e($row['empno']) ?></td>
                        <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= e($row['station_code']) ?></td>
                        <td class="num"><?= peso($row['basic_pay']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>
