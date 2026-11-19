<?php
// dipee/views/dashboard.php
$headcount = find('SELECT COUNT(*) AS `total` FROM `employees` WHERE `archived` = 0')['total'] ?? 0;
$periods = query('SELECT * FROM `payroll_periods` ORDER BY `period_to` DESC, `id` DESC LIMIT 8');
$periods = is_array($periods) ? $periods : [];
$latest = $periods[0] ?? null;
$latestTotals = $latest ? payrollTotals((int) $latest['id']) : ['headcount' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];
$monthlyGross = 0.0;
foreach (query('SELECT * FROM `employees` WHERE `archived` = 0') ?: [] as $employee) {
    $monthlyGross += grossPay($employee);
}
$loanCount = find("SELECT COUNT(*) AS `total` FROM `employee_deductions` WHERE `status` = 'A'")['total'] ?? 0;
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p class="muted">Payroll overview for <?= e(getSetting('division', PAYROLL_SUBTITLE)) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="index.php?page=employees&amp;action=new">Add employee</a>
        <a class="btn btn-primary" href="index.php?page=payroll">Process payroll</a>
    </div>
</div>

<section class="cards">
    <article class="card stat">
        <span class="stat-label">Active employees</span>
        <strong class="stat-value"><?= e(number_format((int) $headcount)) ?></strong>
        <span class="stat-note"><?= e(number_format((int) $loanCount)) ?> active loan deductions</span>
    </article>
    <article class="card stat">
        <span class="stat-label">Monthly gross (master)</span>
        <strong class="stat-value"><?= peso($monthlyGross) ?></strong>
        <span class="stat-note">Basic + allowances of all active employees</span>
    </article>
    <article class="card stat">
        <span class="stat-label">Latest period net pay</span>
        <strong class="stat-value"><?= peso($latestTotals['net']) ?></strong>
        <span class="stat-note"><?= $latest ? e(periodLabel($latest)) : 'No payroll period yet' ?></span>
    </article>
    <article class="card stat">
        <span class="stat-label">Latest deductions</span>
        <strong class="stat-value"><?= peso($latestTotals['deductions']) ?></strong>
        <span class="stat-note">GSIS, PhilHealth, Pag-IBIG, tax and loans</span>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <h2>Recent payroll periods</h2>
        <a class="btn btn-ghost btn-sm" href="index.php?page=payroll">View all</a>
    </div>
    <?php if ($periods === []): ?>
        <p class="empty">No payroll period has been created yet. Start with <a href="index.php?page=payroll">Payroll</a>.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Pay date</th>
                    <th class="num">Employees</th>
                    <th class="num">Gross</th>
                    <th class="num">Deductions</th>
                    <th class="num">Net pay</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periods as $period): ?>
                <?php $totals = payrollTotals((int) $period['id']); ?>
                <tr>
                    <td><?= e(periodLabel($period)) ?></td>
                    <td><?= e(date('M j, Y', strtotime($period['pay_date']))) ?></td>
                    <td class="num"><?= e(number_format((int) $totals['headcount'])) ?></td>
                    <td class="num"><?= peso($totals['gross']) ?></td>
                    <td class="num"><?= peso($totals['deductions']) ?></td>
                    <td class="num"><?= peso($totals['net']) ?></td>
                    <td><span class="badge badge-<?= e($period['status']) ?>"><?= e(ucfirst($period['status'])) ?></span></td>
                    <td class="right"><a href="index.php?page=register&amp;id=<?= (int) $period['id'] ?>">Open register</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <h2>How the rebuilt system computes pay</h2>
    <div class="grid-2">
        <div>
            <h3>Gross and mandatory deductions</h3>
            <ul class="rules">
                <li>Gross = basic + PERA + transport + ACA + RATA + on-top + long pay.</li>
                <li>GSIS life/retirement = <?= e(number_format(payrollSettings()['gsis_rate'] * 100, 1)) ?>% of basic pay.</li>
                <li>PhilHealth = <?= e(number_format(payrollSettings()['phic_rate'] * 100, 1)) ?>% of basic pay.</li>
                <li>Pag-IBIG = <?= peso(payrollSettings()['pagibig']) ?> fixed monthly.</li>
                <li>Withholding tax uses the monthly TRAIN brackets coded in PAYTAX.PRG.</li>
            </ul>
        </div>
        <div>
            <h3>Loan deductions</h3>
            <p class="muted">Loans are applied in priority order. As in PAYRINCL.PRG, a deduction is skipped
                when it would push the employee's net pay below <?= peso(payrollSettings()['net_floor']) ?>.
                Skipped deductions are recorded on the payroll register with the reason.</p>
            <p class="muted">Taxable income = gross &minus; GSIS &minus; PhilHealth &minus; Pag-IBIG.</p>
        </div>
    </div>
</section>
