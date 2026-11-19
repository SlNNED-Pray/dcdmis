<?php
// dipee/views/payroll.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['create-period'])) {
        $type = in_array($_POST['type'] ?? '', ['regular', 'supplementary', 'special'], true) ? $_POST['type'] : 'regular';
        $from = trim((string) ($_POST['period_from'] ?? ''));
        $to = trim((string) ($_POST['period_to'] ?? ''));
        $payDate = trim((string) ($_POST['pay_date'] ?? ''));
        if ($from === '' || $to === '' || $payDate === '') {
            flash('error', 'Period start, period end and pay date are required.');
        } elseif ($to < $from) {
            flash('error', 'The period end cannot be earlier than the period start.');
        } else {
            $periodId = insert('payroll_periods', [
                'type' => $type,
                'period_from' => $from,
                'period_to' => $to,
                'pay_date' => $payDate,
                'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                'status' => 'draft',
                'created_by' => (int) ($user['id'] ?? 0) ?: null,
            ]);
            flash('success', 'Payroll period created. Generate it when the master data is ready.');
            redirect('index.php?page=payroll&id=' . (int) $periodId);
        }
        redirect('index.php?page=payroll');
    }

    if (isset($_POST['generate'])) {
        $result = generatePayroll((int) ($_POST['id'] ?? 0));
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('index.php?page=register&id=' . (int) ($_POST['id'] ?? 0));
    }

    if (isset($_POST['finalize'])) {
        $periodId = (int) ($_POST['id'] ?? 0);
        $totals = payrollTotals($periodId);
        if ((int) $totals['headcount'] === 0) {
            flash('error', 'Generate the payroll before finalizing it.');
        } else {
            update('payroll_periods', ['status' => 'final', 'finalized_at' => date('Y-m-d H:i:s')], '`id` = ?', [$periodId]);
            flash('success', 'Payroll finalized. It is now locked for editing.');
        }
        redirect('index.php?page=register&id=' . $periodId);
    }

    if (isset($_POST['reopen'])) {
        $periodId = (int) ($_POST['id'] ?? 0);
        update('payroll_periods', ['status' => 'draft', 'finalized_at' => null], '`id` = ?', [$periodId]);
        flash('success', 'Payroll reopened as a draft.');
        redirect('index.php?page=register&id=' . $periodId);
    }

    if (isset($_POST['delete-period'])) {
        $periodId = (int) ($_POST['id'] ?? 0);
        $period = payrollPeriod($periodId);
        if (!$period || $period['status'] !== 'draft') {
            flash('error', 'Only draft periods can be deleted.');
        } else {
            delete('payroll_periods', '`id` = ?', [$periodId]);
            flash('success', 'Draft payroll period deleted.');
        }
        redirect('index.php?page=payroll');
    }
}

$periods = query('SELECT * FROM `payroll_periods` ORDER BY `period_to` DESC, `id` DESC');
$periods = is_array($periods) ? $periods : [];
?>
<div class="page-head">
    <div>
        <h1>Payroll</h1>
        <p class="muted">Regular, supplementary and special payroll runs</p>
    </div>
</div>

<div class="grid-sidebar">
    <section class="card">
        <div class="card-head">
            <h2>Payroll periods</h2>
        </div>
        <?php if ($periods === []): ?>
            <p class="empty">No payroll period yet. Create one on the right.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Period</th><th>Pay date</th><th class="num">Employees</th>
                        <th class="num">Net pay</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $period): ?>
                    <?php $totals = payrollTotals((int) $period['id']); ?>
                    <tr>
                        <td><?= e(periodLabel($period)) ?></td>
                        <td><?= e(date('M j, Y', strtotime($period['pay_date']))) ?></td>
                        <td class="num"><?= e(number_format((int) $totals['headcount'])) ?></td>
                        <td class="num"><?= peso($totals['net']) ?></td>
                        <td><span class="badge badge-<?= e($period['status']) ?>"><?= e(ucfirst($period['status'])) ?></span></td>
                        <td class="right nowrap">
                            <?php if ($period['status'] === 'draft'): ?>
                                <form method="post" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $period['id'] ?>">
                                    <button class="btn btn-primary btn-sm" type="submit" name="generate" value="1">
                                        <?= (int) $totals['headcount'] > 0 ? 'Regenerate' : 'Generate' ?>
                                    </button>
                                </form>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this draft payroll?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $period['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" name="delete-period" value="1">Delete</button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-ghost btn-sm" href="index.php?page=register&amp;id=<?= (int) $period['id'] ?>">Open</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>New payroll period</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <label>
                Type
                <select name="type">
                    <option value="regular">Regular payroll</option>
                    <option value="supplementary">Supplementary payroll</option>
                    <option value="special">Special payroll</option>
                </select>
            </label>
            <label>Period start <input type="date" name="period_from" required></label>
            <label>Period end <input type="date" name="period_to" required></label>
            <label>Pay date <input type="date" name="pay_date" required></label>
            <label>Notes <input type="text" name="notes" maxlength="255"></label>
            <button class="btn btn-primary" type="submit" name="create-period" value="1">Create period</button>
        </form>
        <p class="muted small">Generating uses the current master records, the configured rates and the
            active loan deductions. Re-generating replaces the draft lines.</p>
    </section>
</div>
