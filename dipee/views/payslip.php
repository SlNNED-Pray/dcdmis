<?php
// dipee/views/payslip.php
$itemId = (int) ($_GET['item'] ?? 0);
$item = $itemId ? find(
    'SELECT i.*, p.`period_from`, p.`period_to`, p.`pay_date`, p.`type`, p.`status`,
            e.`empno`, e.`last_name`, e.`first_name`, e.`middle_name`, e.`position`,
            e.`station_code`, e.`grade`, e.`step`, e.`gsis_pn`, e.`phic_id`, e.`hdmf_id`, e.`tin`, e.`account_no`
     FROM `payroll_items` i
     INNER JOIN `payroll_periods` p ON p.`id` = i.`period_id`
     INNER JOIN `employees` e ON e.`id` = i.`employee_id`
     WHERE i.`id` = ? LIMIT 1',
    [$itemId]
) : false;

if (!$item) {
    echo '<p class="empty">Payslip not found. <a href="index.php?page=payroll">Back to payroll</a></p>';
    return;
}

$loans = payrollDeductionItems($itemId);
$applied = array_filter($loans, fn ($row) => (int) $row['included'] === 1);
$excluded = array_filter($loans, fn ($row) => (int) $row['included'] === 0);
$earnings = [
    'Basic pay' => (float) $item['basic_pay'],
    'PERA' => (float) $item['pera'],
    'Transport allowance' => (float) $item['trans_allow'],
    'ACA' => (float) $item['aca'],
    'RATA' => (float) $item['rata'],
    'On-top' => (float) $item['ontop'],
    'Long pay' => (float) $item['long_pay'],
];
?>
<div class="page-head no-print">
    <div>
        <h1>Payslip</h1>
        <p class="muted"><?= e(employeeName($item) . ' · ' . $item['empno']) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="index.php?page=register&amp;id=<?= (int) $item['period_id'] ?>">Back to register</a>
        <button class="btn btn-primary" type="button" data-print>Print payslip</button>
    </div>
</div>

<section class="payslip print-area">
    <header class="payslip-head">
        <div>
            <h2><?= e(getSetting('org_name', 'Department of Education')) ?></h2>
            <p><?= e(getSetting('region')) ?></p>
            <p class="strong"><?= e(getSetting('division')) ?></p>
        </div>
        <div class="payslip-title">
            <h3>PAYSLIP</h3>
            <p><?= e(ucfirst($item['type'])) ?> payroll</p>
            <p><?= e(date('M j, Y', strtotime($item['period_from']))) ?> – <?= e(date('M j, Y', strtotime($item['period_to']))) ?></p>
        </div>
    </header>

    <dl class="payslip-meta">
        <div><dt>Employee</dt><dd><?= e(employeeName($item)) ?></dd></div>
        <div><dt>Employee no.</dt><dd class="mono"><?= e($item['empno']) ?></dd></div>
        <div><dt>Position</dt><dd><?= e($item['position']) ?></dd></div>
        <div><dt>Station</dt><dd><?= e(stationName($item['station_code'])) ?></dd></div>
        <div><dt>Salary grade / step</dt><dd><?= e(trim(($item['grade'] ?? '') . ' / ' . ($item['step'] ?? ''), ' /')) ?></dd></div>
        <div><dt>Pay date</dt><dd><?= e(date('M j, Y', strtotime($item['pay_date']))) ?></dd></div>
    </dl>

    <div class="payslip-columns">
        <table class="table table-compact">
            <thead><tr><th colspan="2">Earnings</th></tr></thead>
            <tbody>
            <?php foreach ($earnings as $label => $amount): ?>
                <?php if ($amount > 0): ?>
                    <tr><td><?= e($label) ?></td><td class="num"><?= peso($amount, false) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total"><th>Gross pay</th><th class="num"><?= peso($item['gross_pay'], false) ?></th></tr>
            </tfoot>
        </table>

        <table class="table table-compact">
            <thead><tr><th colspan="2">Deductions</th></tr></thead>
            <tbody>
                <tr><td>GSIS life / retirement</td><td class="num"><?= peso($item['gsis'], false) ?></td></tr>
                <tr><td>PhilHealth</td><td class="num"><?= peso($item['philhealth'], false) ?></td></tr>
                <tr><td>Pag-IBIG</td><td class="num"><?= peso($item['pagibig'], false) ?></td></tr>
                <tr><td>Withholding tax</td><td class="num"><?= peso($item['tax'], false) ?></td></tr>
                <?php foreach ($applied as $loan): ?>
                    <tr><td><?= e($loan['label']) ?></td><td class="num"><?= peso($loan['amount'], false) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total"><th>Total deductions</th><th class="num"><?= peso($item['total_deductions'], false) ?></th></tr>
            </tfoot>
        </table>
    </div>

    <div class="payslip-net">
        <span>NET PAY</span>
        <strong><?= peso($item['net_pay']) ?></strong>
    </div>

    <?php if ($excluded !== []): ?>
        <p class="muted small no-print">
            Skipped deductions (net pay would fall below <?= peso(payrollSettings()['net_floor']) ?>):
            <?php $labels = []; ?>
            <?php foreach ($excluded as $loan): ?>
                <?php $labels[] = $loan['label'] . ' (' . peso($loan['amount']) . ')'; ?>
            <?php endforeach; ?>
            <?= e(implode(', ', $labels)) ?>
        </p>
    <?php endif; ?>

    <p class="muted small"><?= e(getSetting('payslip_note')) ?></p>

    <div class="signatures">
        <div><span class="line"></span><small>Prepared by</small></div>
        <div><span class="line"></span><small>Certified correct</small></div>
        <div><span class="line"></span><small>Received by</small></div>
    </div>
</section>
