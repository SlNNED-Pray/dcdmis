<?php
// dipee/views/register.php
$periodId = (int) ($_GET['id'] ?? 0);
$period = payrollPeriod($periodId);

if (!$period) {
    echo '<p class="empty">Payroll period not found. <a href="index.php?page=payroll">Back to payroll</a></p>';
    return;
}

$items = payrollItems($periodId);
$totals = payrollTotals($periodId);
$sum = ['gross' => 0, 'gsis' => 0, 'philhealth' => 0, 'pagibig' => 0, 'tax' => 0, 'other' => 0, 'deductions' => 0, 'net' => 0];
foreach ($items as $item) {
    $sum['gross'] += (float) $item['gross_pay'];
    $sum['gsis'] += (float) $item['gsis'];
    $sum['philhealth'] += (float) $item['philhealth'];
    $sum['pagibig'] += (float) $item['pagibig'];
    $sum['tax'] += (float) $item['tax'];
    $sum['other'] += (float) $item['other_deductions'];
    $sum['deductions'] += (float) $item['total_deductions'];
    $sum['net'] += (float) $item['net_pay'];
}
?>
<div class="page-head">
    <div>
        <h1>Payroll register</h1>
        <p class="muted">
            <?= e(periodLabel($period)) ?> · Pay date <?= e(date('M j, Y', strtotime($period['pay_date']))) ?>
            · <span class="badge badge-<?= e($period['status']) ?>"><?= e(ucfirst($period['status'])) ?></span>
        </p>
    </div>
    <div class="page-actions no-print">
        <a class="btn btn-ghost" href="index.php?page=payroll">Back</a>
        <?php if ($period['status'] === 'draft'): ?>
            <form method="post" action="index.php?page=payroll" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $period['id'] ?>">
                <button class="btn btn-primary" type="submit" name="generate" value="1">Regenerate</button>
            </form>
            <form method="post" action="index.php?page=payroll" class="inline" onsubmit="return confirm('Finalize this payroll? It will be locked for editing.');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $period['id'] ?>">
                <button class="btn btn-primary" type="submit" name="finalize" value="1">Finalize</button>
            </form>
        <?php else: ?>
            <form method="post" action="index.php?page=payroll" class="inline" onsubmit="return confirm('Reopen this finalized payroll as a draft?');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $period['id'] ?>">
                <button class="btn btn-ghost" type="submit" name="reopen" value="1">Reopen</button>
            </form>
        <?php endif; ?>
        <button class="btn btn-ghost" type="button" data-print>Print</button>
    </div>
</div>

<?php if ($items === []): ?>
    <section class="card">
        <p class="empty">This period has no payroll lines yet.
            <form method="post" action="index.php?page=payroll" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $period['id'] ?>">
                <button class="btn btn-primary btn-sm" type="submit" name="generate" value="1">Generate payroll</button>
            </form>
        </p>
    </section>
<?php else: ?>
    <section class="card print-area">
        <div class="print-head">
            <h2><?= e(getSetting('org_name', 'Department of Education')) ?></h2>
            <p><?= e(getSetting('region')) ?> · <?= e(getSetting('division')) ?></p>
            <h3>Payroll Register — <?= e(periodLabel($period)) ?></h3>
        </div>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th>#</th><th>Employee</th><th>Position / Station</th>
                    <th class="num">Gross</th><th class="num">GSIS</th><th class="num">PHIC</th>
                    <th class="num">HDMF</th><th class="num">Tax</th><th class="num">Loans</th>
                    <th class="num">Total ded.</th><th class="num">Net pay</th><th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <?= e(employeeName($item)) ?><br>
                        <span class="mono small"><?= e($item['empno']) ?></span>
                    </td>
                    <td class="small"><?= e($item['position']) ?><br><span class="muted"><?= e(stationName($item['station_code'])) ?></span></td>
                    <td class="num"><?= peso($item['gross_pay'], false) ?></td>
                    <td class="num"><?= peso($item['gsis'], false) ?></td>
                    <td class="num"><?= peso($item['philhealth'], false) ?></td>
                    <td class="num"><?= peso($item['pagibig'], false) ?></td>
                    <td class="num"><?= peso($item['tax'], false) ?></td>
                    <td class="num"><?= peso($item['other_deductions'], false) ?></td>
                    <td class="num"><?= peso($item['total_deductions'], false) ?></td>
                    <td class="num strong"><?= peso($item['net_pay'], false) ?></td>
                    <td class="no-print right"><a href="index.php?page=payslip&amp;item=<?= (int) $item['id'] ?>">Payslip</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total">
                    <th colspan="3">Totals · <?= e(number_format(count($items))) ?> employee(s)</th>
                    <th class="num"><?= peso($sum['gross'], false) ?></th>
                    <th class="num"><?= peso($sum['gsis'], false) ?></th>
                    <th class="num"><?= peso($sum['philhealth'], false) ?></th>
                    <th class="num"><?= peso($sum['pagibig'], false) ?></th>
                    <th class="num"><?= peso($sum['tax'], false) ?></th>
                    <th class="num"><?= peso($sum['other'], false) ?></th>
                    <th class="num"><?= peso($sum['deductions'], false) ?></th>
                    <th class="num"><?= peso($sum['net'], false) ?></th>
                    <th class="no-print"></th>
                </tr>
            </tfoot>
        </table>
        <p class="muted small">
            Grand total net pay: <?= peso($totals['net']) ?> ·
            Generated <?= e($period['generated_at'] ? date('M j, Y g:i A', strtotime($period['generated_at'])) : '—') ?>
            <?= $period['finalized_at'] ? ' · Finalized ' . e(date('M j, Y g:i A', strtotime($period['finalized_at']))) : '' ?>
        </p>
    </section>
<?php endif; ?>
