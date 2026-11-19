<?php
// dipee/views/employee-form.php
$id = (int) ($_GET['id'] ?? 0);
$record = $id ? employee($id) : false;
if ($id && !$record) {
    echo '<p class="empty">That employee record does not exist.</p>';
    return;
}

$old = $record ?: [
    'empno' => '', 'last_name' => '', 'first_name' => '', 'middle_name' => '',
    'station_code' => '', 'position' => '', 'grade' => '', 'step' => '',
    'basic_pay' => 0, 'pera' => 0, 'trans_allow' => 0, 'aca' => 0, 'rata' => 0,
    'ontop' => 0, 'long_pay' => 0, 'taxcode' => '03', 'status' => 'P',
    'paymode' => 'A', 'bank' => '', 'account_no' => '', 'gsis_pn' => '',
    'phic_id' => '', 'hdmf_id' => '', 'tin' => '', 'hire_date' => '',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save-employee'])) {
    verifyCsrf();
    $data = [
        'empno' => trim((string) ($_POST['empno'] ?? '')),
        'last_name' => trim((string) ($_POST['last_name'] ?? '')),
        'first_name' => trim((string) ($_POST['first_name'] ?? '')),
        'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
        'station_code' => trim((string) ($_POST['station_code'] ?? '')) ?: null,
        'position' => trim((string) ($_POST['position'] ?? '')),
        'grade' => trim((string) ($_POST['grade'] ?? '')),
        'step' => trim((string) ($_POST['step'] ?? '')),
        'basic_pay' => num($_POST['basic_pay'] ?? 0),
        'pera' => num($_POST['pera'] ?? 0),
        'trans_allow' => num($_POST['trans_allow'] ?? 0),
        'aca' => num($_POST['aca'] ?? 0),
        'rata' => num($_POST['rata'] ?? 0),
        'ontop' => num($_POST['ontop'] ?? 0),
        'long_pay' => num($_POST['long_pay'] ?? 0),
        'taxcode' => trim((string) ($_POST['taxcode'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'P')),
        'paymode' => trim((string) ($_POST['paymode'] ?? 'A')),
        'bank' => trim((string) ($_POST['bank'] ?? '')),
        'account_no' => trim((string) ($_POST['account_no'] ?? '')),
        'gsis_pn' => trim((string) ($_POST['gsis_pn'] ?? '')),
        'phic_id' => trim((string) ($_POST['phic_id'] ?? '')),
        'hdmf_id' => trim((string) ($_POST['hdmf_id'] ?? '')),
        'tin' => trim((string) ($_POST['tin'] ?? '')),
        'hire_date' => trim((string) ($_POST['hire_date'] ?? '')) ?: null,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $old = array_merge($old, $data);

    if ($data['empno'] === '' || $data['last_name'] === '' || $data['first_name'] === '') {
        $errors[] = 'Employee number, last name and first name are required.';
    }
    $duplicate = find('SELECT `id` FROM `employees` WHERE `empno` = ? AND `id` <> ? LIMIT 1', [$data['empno'], $id]);
    if ($duplicate) {
        $errors[] = 'That employee number is already used by another record.';
    }
    if ($data['basic_pay'] < 0) {
        $errors[] = 'Basic pay cannot be negative.';
    }

    if ($errors === []) {
        if ($id) {
            update('employees', $data, '`id` = ?', [$id]);
            flash('success', 'Employee record updated.');
        } else {
            $newId = insert('employees', $data + ['created_at' => date('Y-m-d H:i:s')]);
            flash('success', 'Employee record created.');
            $id = (int) $newId;
        }
        redirect('index.php?page=employee-form&id=' . $id);
    }
}

$preview = null;
if ($record) {
    $preview = computePayrollLine($record, payrollSettings(), taxBrackets(), date('Y-m-d'));
}
?>
<div class="page-head">
    <div>
        <h1><?= $record ? 'Edit employee' : 'New employee' ?></h1>
        <p class="muted"><?= $record ? e(employeeName($record) . ' · ' . $record['empno']) : 'Create a master record' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="index.php?page=employees">Back to list</a>
        <?php if ($record): ?>
            <a class="btn btn-ghost" href="index.php?page=deductions&amp;employee_id=<?= (int) $record['id'] ?>">Manage loans</a>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="card">
    <form method="post" class="form-grid">
        <?= csrfField() ?>
        <label>
            Employee number *
            <input type="text" name="empno" value="<?= e($old['empno']) ?>" required>
        </label>
        <label>
            Last name *
            <input type="text" name="last_name" value="<?= e($old['last_name']) ?>" required>
        </label>
        <label>
            First name *
            <input type="text" name="first_name" value="<?= e($old['first_name']) ?>" required>
        </label>
        <label>
            Middle name
            <input type="text" name="middle_name" value="<?= e($old['middle_name']) ?>">
        </label>
        <label>
            Station
            <select name="station_code">
                <option value="">—</option>
                <?php foreach (stations() as $row): ?>
                    <option value="<?= e($row['code']) ?>" <?= $old['station_code'] === $row['code'] ? 'selected' : '' ?>>
                        <?= e($row['code'] . ' · ' . $row['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Position
            <input type="text" name="position" value="<?= e($old['position']) ?>">
        </label>
        <label>
            Salary grade
            <input type="text" name="grade" value="<?= e($old['grade']) ?>" maxlength="4">
        </label>
        <label>
            Step
            <input type="text" name="step" value="<?= e($old['step']) ?>" maxlength="4">
        </label>

        <h3 class="span-all">Compensation</h3>
        <label>Basic pay <input type="number" step="0.01" min="0" name="basic_pay" value="<?= e($old['basic_pay']) ?>"></label>
        <label>PERA <input type="number" step="0.01" min="0" name="pera" value="<?= e($old['pera']) ?>"></label>
        <label>Transport allowance <input type="number" step="0.01" min="0" name="trans_allow" value="<?= e($old['trans_allow']) ?>"></label>
        <label>ACA <input type="number" step="0.01" min="0" name="aca" value="<?= e($old['aca']) ?>"></label>
        <label>RATA <input type="number" step="0.01" min="0" name="rata" value="<?= e($old['rata']) ?>"></label>
        <label>On-top <input type="number" step="0.01" min="0" name="ontop" value="<?= e($old['ontop']) ?>"></label>
        <label>Long pay <input type="number" step="0.01" min="0" name="long_pay" value="<?= e($old['long_pay']) ?>"></label>

        <h3 class="span-all">Payroll details</h3>
        <label>
            Tax code
            <select name="taxcode">
                <?php foreach (['01' => 'Single', '02' => 'Head of family', '03' => 'Married'] as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $old['taxcode'] === $code ? 'selected' : '' ?>><?= e($code . ' · ' . $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Employment status
            <select name="status">
                <?php foreach (['P' => 'Permanent', 'C' => 'Casual', 'T' => 'Temporary', 'CT' => 'Contractual'] as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $old['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Payment mode
            <select name="paymode">
                <option value="A" <?= $old['paymode'] === 'A' ? 'selected' : '' ?>>ATM</option>
                <option value="C" <?= $old['paymode'] === 'C' ? 'selected' : '' ?>>Check</option>
            </select>
        </label>
        <label>Bank <input type="text" name="bank" value="<?= e($old['bank']) ?>"></label>
        <label>ATM account number <input type="text" name="account_no" value="<?= e($old['account_no']) ?>"></label>
        <label>Date hired <input type="date" name="hire_date" value="<?= e($old['hire_date']) ?>"></label>

        <h3 class="span-all">Government numbers</h3>
        <label>GSIS policy number <input type="text" name="gsis_pn" value="<?= e($old['gsis_pn']) ?>"></label>
        <label>PhilHealth number <input type="text" name="phic_id" value="<?= e($old['phic_id']) ?>"></label>
        <label>Pag-IBIG number <input type="text" name="hdmf_id" value="<?= e($old['hdmf_id']) ?>"></label>
        <label>TIN <input type="text" name="tin" value="<?= e($old['tin']) ?>"></label>

        <div class="span-all form-actions">
            <button class="btn btn-primary" type="submit" name="save-employee" value="1">Save employee</button>
        </div>
    </form>
</section>

<?php if ($preview): ?>
<section class="card">
    <h2>Computed monthly pay</h2>
    <div class="grid-2">
        <table class="table table-compact">
            <tbody>
                <tr><th>Gross pay</th><td class="num"><?= peso($preview['gross_pay']) ?></td></tr>
                <tr><th>GSIS</th><td class="num"><?= peso($preview['gsis']) ?></td></tr>
                <tr><th>PhilHealth</th><td class="num"><?= peso($preview['philhealth']) ?></td></tr>
                <tr><th>Pag-IBIG</th><td class="num"><?= peso($preview['pagibig']) ?></td></tr>
                <tr><th>Withholding tax</th><td class="num"><?= peso($preview['tax']) ?></td></tr>
                <tr><th>Loan deductions</th><td class="num"><?= peso($preview['other_deductions']) ?></td></tr>
                <tr class="total"><th>Net pay</th><td class="num"><?= peso($preview['net_pay']) ?></td></tr>
            </tbody>
        </table>
        <div>
            <h3>Loan deductions applied</h3>
            <?php if ($preview['applied'] === [] && $preview['excluded'] === []): ?>
                <p class="muted">No active loan deductions.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($preview['applied'] as $loan): ?>
                        <li><span><?= e($loan['name']) ?></span><span><?= peso($loan['amount']) ?></span></li>
                    <?php endforeach; ?>
                    <?php foreach ($preview['excluded'] as $loan): ?>
                        <li class="muted"><span><?= e($loan['name']) ?> (skipped)</span><span><?= peso($loan['amount']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="muted small">Taxable income: <?= peso($preview['taxable']) ?> · computed from the saved values above.</p>
        </div>
    </div>
</section>
<?php endif; ?>
