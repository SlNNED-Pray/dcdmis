<?php
// dipee/views/deductions.php
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save-type'])) {
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $type = [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'kind' => in_array($_POST['kind'] ?? '', ['loan', 'premium', 'other'], true) ? $_POST['kind'] : 'loan',
            'agency' => trim((string) ($_POST['agency'] ?? '')) ?: null,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        if ($type['code'] === '' || $type['name'] === '') {
            flash('error', 'Deduction code and name are required.');
        } elseif (find('SELECT `id` FROM `deduction_types` WHERE `code` = ? AND `id` <> ?', [$type['code'], $typeId])) {
            flash('error', 'That deduction code already exists.');
        } elseif ($typeId) {
            update('deduction_types', $type, '`id` = ?', [$typeId]);
            flash('success', 'Deduction type updated.');
        } else {
            insert('deduction_types', $type);
            flash('success', 'Deduction type created.');
        }
        redirect('index.php?page=deductions');
    }

    if (isset($_POST['save-assignment'])) {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $typeId = (int) ($_POST['deduction_type_id'] ?? 0);
        $amount = num($_POST['amount'] ?? 0);
        if (!$employeeId || !$typeId) {
            flash('error', 'Select an employee and a deduction type.');
        } elseif ($amount <= 0) {
            flash('error', 'The deduction amount must be greater than zero.');
        } else {
            insert('employee_deductions', [
                'employee_id' => $employeeId,
                'deduction_type_id' => $typeId,
                'amount' => $amount,
                'policy_no' => trim((string) ($_POST['policy_no'] ?? '')) ?: null,
                'effective_from' => trim((string) ($_POST['effective_from'] ?? '')) ?: null,
                'effective_to' => trim((string) ($_POST['effective_to'] ?? '')) ?: null,
                'priority' => max(1, (int) ($_POST['priority'] ?? 1)),
                'status' => 'A',
            ]);
            flash('success', 'Loan deduction added.');
        }
        redirect('index.php?page=deductions&employee_id=' . $employeeId);
    }

    if (isset($_POST['delete-assignment'])) {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        delete('employee_deductions', '`id` = ?', [$assignmentId]);
        flash('success', 'Loan deduction removed.');
        redirect('index.php?page=deductions');
    }
}

$employeeId = (int) ($_GET['employee_id'] ?? 0);
$editType = (int) ($_GET['type_id'] ?? 0);
$type = $editType ? find('SELECT * FROM `deduction_types` WHERE `id` = ?', [$editType]) : false;

$assignments = query(
    'SELECT d.*, t.`code`, t.`name` AS `type_name`, t.`agency`,
            e.`empno`, e.`last_name`, e.`first_name`, e.`middle_name`
     FROM `employee_deductions` d
     INNER JOIN `deduction_types` t ON t.`id` = d.`deduction_type_id`
     INNER JOIN `employees` e ON e.`id` = d.`employee_id`
     ' . ($employeeId ? 'WHERE d.`employee_id` = ?' : '') . '
     ORDER BY e.`last_name` ASC, d.`priority` ASC',
    $employeeId ? [$employeeId] : []
);
$assignments = is_array($assignments) ? $assignments : [];
$employees = query('SELECT * FROM `employees` WHERE `archived` = 0 ORDER BY `last_name` ASC');
$employees = is_array($employees) ? $employees : [];
$selectedEmployee = $employeeId ? employee($employeeId) : false;
?>
<div class="page-head">
    <div>
        <h1>Deductions</h1>
        <p class="muted">Deduction types (DEDTABLE) and employee loans (DEDFILE)</p>
    </div>
</div>

<div class="grid-2 align-start">
    <section class="card">
        <div class="card-head">
            <h2><?= $type ? 'Edit deduction type' : 'New deduction type' ?></h2>
            <?php if ($type): ?>
                <a class="btn btn-ghost btn-sm" href="index.php?page=deductions">Cancel</a>
            <?php endif; ?>
        </div>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <input type="hidden" name="type_id" value="<?= (int) ($type['id'] ?? 0) ?>">
            <label>Code <input type="text" name="code" maxlength="5" value="<?= e($type['code'] ?? '') ?>" required></label>
            <label>Name <input type="text" name="name" maxlength="120" value="<?= e($type['name'] ?? '') ?>" required></label>
            <label>
                Kind
                <select name="kind">
                    <?php foreach (['loan' => 'Loan', 'premium' => 'Premium', 'other' => 'Other'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($type['kind'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Agency <input type="text" name="agency" maxlength="30" value="<?= e($type['agency'] ?? '') ?>"></label>
            <label class="checkbox">
                <input type="checkbox" name="active" value="1" <?= !isset($type['active']) || $type['active'] ? 'checked' : '' ?>>
                Active
            </label>
            <button class="btn btn-primary" type="submit" name="save-type" value="1">Save type</button>
        </form>

        <table class="table table-compact">
            <thead><tr><th>Code</th><th>Name</th><th>Agency</th><th></th></tr></thead>
            <tbody>
            <?php foreach (deductionTypes(false) as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['code']) ?></td>
                    <td><?= e($row['name']) ?><?= $row['active'] ? '' : ' <span class="badge badge-draft">Inactive</span>' ?></td>
                    <td><?= e($row['agency']) ?></td>
                    <td class="right"><a href="index.php?page=deductions&amp;type_id=<?= (int) $row['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2><?= $selectedEmployee ? 'Loans of ' . e(employeeName($selectedEmployee)) : 'Employee loan deductions' ?></h2>
        <?php if ($selectedEmployee): ?>
            <p class="muted small">Showing only <?= e($selectedEmployee['empno']) ?>.
                <a href="index.php?page=deductions">Show all</a></p>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrfField() ?>
            <label class="span-all">
                Employee
                <select name="employee_id" required>
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= $employeeId === (int) $row['id'] ? 'selected' : '' ?>>
                            <?= e($row['empno'] . ' · ' . employeeName($row)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Deduction type
                <select name="deduction_type_id" required>
                    <option value="">Select type</option>
                    <?php foreach (deductionTypes() as $row): ?>
                        <option value="<?= (int) $row['id'] ?>"><?= e($row['code'] . ' · ' . $row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Monthly amount <input type="number" name="amount" min="0.01" step="0.01" required></label>
            <label>Policy / reference no. <input type="text" name="policy_no"></label>
            <label>Priority <input type="number" name="priority" min="1" value="1"></label>
            <label>Effective from <input type="date" name="effective_from"></label>
            <label>Effective until <input type="date" name="effective_to"></label>
            <div class="span-all form-actions">
                <button class="btn btn-primary" type="submit" name="save-assignment" value="1">Add loan deduction</button>
            </div>
        </form>

        <?php if ($assignments === []): ?>
            <p class="empty">No loan deductions recorded.</p>
        <?php else: ?>
            <table class="table table-compact">
                <thead>
                    <tr><th>Employee</th><th>Deduction</th><th class="num">Amount</th><th>Effective</th><th>Priority</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $row): ?>
                    <tr>
                        <td><?= e(employeeName($row)) ?><br><span class="mono small"><?= e($row['empno']) ?></span></td>
                        <td><?= e($row['type_name']) ?><br><span class="muted small"><?= e($row['agency']) ?></span></td>
                        <td class="num"><?= peso($row['amount']) ?></td>
                        <td class="small">
                            <?= e($row['effective_from'] ? date('M Y', strtotime($row['effective_from'])) : '—') ?>
                            to <?= e($row['effective_to'] ? date('M Y', strtotime($row['effective_to'])) : '—') ?>
                        </td>
                        <td><?= (int) $row['priority'] ?></td>
                        <td class="right">
                            <form method="post" class="inline" onsubmit="return confirm('Remove this loan deduction?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="assignment_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-ghost btn-sm" type="submit" name="delete-assignment" value="1">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
