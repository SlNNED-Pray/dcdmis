<?php
// dipee/functions.php - shared helpers and the payroll computation engine.
// The formulas come from the legacy FoxPro programs:
//   PAYMRG/PAYRMRG : gross = basic + trans + pera + aca (+ ontop + long + rata)
//                    mandatory = gsis + philhealth + pagibig + tax
//                    take home  = gross - (mandatory + other deductions)
//   PAYTAX         : monthly TRAIN withholding tax brackets
//   PAYRINCL       : loan deductions stop when net pay would fall below 5,000

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function peso(float|int|string|null $amount, bool $withSymbol = true): string
{
    $formatted = number_format((float) $amount, 2, '.', ',');
    return $withSymbol ? '₱' . $formatted : $formatted;
}

function num(mixed $value): float
{
    return round((float) str_replace(',', '', (string) $value), 2);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['dipee_csrf'])) {
        $_SESSION['dipee_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['dipee_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['dipee_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Session expired. Please reload the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['dipee_flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash(): ?array
{
    if (empty($_SESSION['dipee_flash'])) {
        return null;
    }
    $flash = $_SESSION['dipee_flash'];
    unset($_SESSION['dipee_flash']);
    return $flash;
}

function getSetting(string $key, string $default = ''): string
{
    $row = find('SELECT `svalue` FROM `settings` WHERE `skey` = ? LIMIT 1', [$key]);
    return $row ? (string) $row['svalue'] : $default;
}

function setSetting(string $key, string $value): void
{
    execute(
        'INSERT INTO `settings` (`skey`, `svalue`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `svalue` = VALUES(`svalue`)',
        [$key, $value]
    );
}

function payrollSettings(): array
{
    return [
        'gsis_rate' => (float) getSetting('gsis_rate', '0.09'),
        'phic_rate' => (float) getSetting('phic_rate', '0.025'),
        'pagibig' => (float) getSetting('pagibig_amount', '200'),
        'net_floor' => (float) getSetting('net_floor', '5000'),
    ];
}

function taxBrackets(): array
{
    $rows = query('SELECT * FROM `tax_brackets` ORDER BY `sort_order` ASC');
    return is_array($rows) ? $rows : [];
}

// Monthly withholding tax, exactly the brackets coded in PAYTAX.PRG.
function computeTax(float $taxable, array $brackets): float
{
    if ($taxable <= 0) {
        return 0.0;
    }
    foreach ($brackets as $bracket) {
        $lower = (float) $bracket['lower_bound'];
        $upper = $bracket['upper_bound'] === null ? null : (float) $bracket['upper_bound'];
        if ($taxable >= $lower && ($upper === null || $taxable < $upper)) {
            $tax = (float) $bracket['base_tax'] + ($taxable - $lower) * (float) $bracket['rate'];
            return round(max(0, $tax), 2);
        }
    }
    return 0.0;
}

function grossPay(array $employee): float
{
    return num($employee['basic_pay'])
        + num($employee['pera'])
        + num($employee['trans_allow'])
        + num($employee['aca'])
        + num($employee['rata'])
        + num($employee['ontop'])
        + num($employee['long_pay']);
}

function stations(): array
{
    $rows = query('SELECT * FROM `stations` ORDER BY `code` ASC');
    return is_array($rows) ? $rows : [];
}

function stationName(?string $code): string
{
    if ($code === null || $code === '') {
        return '';
    }
    $row = find('SELECT `name` FROM `stations` WHERE `code` = ? LIMIT 1', [$code]);
    return $row ? $row['name'] : $code;
}

function employee(int $id): array|false
{
    return find('SELECT * FROM `employees` WHERE `id` = ? LIMIT 1', [$id]);
}

function employeeName(array $employee): string
{
    $middle = trim((string) ($employee['middle_name'] ?? ''));
    $initial = $middle !== '' ? ' ' . strtoupper(substr($middle, 0, 1)) . '.' : '';
    return trim(($employee['last_name'] ?? '') . ', ' . ($employee['first_name'] ?? '') . $initial);
}

function deductionTypes(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM `deduction_types`' . ($activeOnly ? ' WHERE `active` = 1' : '') . ' ORDER BY `code` ASC';
    $rows = query($sql);
    return is_array($rows) ? $rows : [];
}

function employeeDeductions(int $employeeId, ?string $asOf = null): array
{
    $sql = 'SELECT d.*, t.`code`, t.`name`, t.`kind`, t.`agency`
            FROM `employee_deductions` d
            INNER JOIN `deduction_types` t ON t.`id` = d.`deduction_type_id`
            WHERE d.`employee_id` = ? AND d.`status` = \'A\'';
    $params = [$employeeId];
    if ($asOf !== null) {
        $sql .= ' AND (d.`effective_from` IS NULL OR d.`effective_from` <= ?)
                  AND (d.`effective_to` IS NULL OR d.`effective_to` >= ?)';
        $params[] = $asOf;
        $params[] = $asOf;
    }
    $sql .= ' ORDER BY d.`priority` ASC, t.`code` ASC';
    $rows = query($sql, $params);
    return is_array($rows) ? $rows : [];
}

/**
 * Compute one employee's payroll line.
 * Returns gross, mandatory deductions, tax, applied/excluded loan deductions and net pay.
 */
function computePayrollLine(array $employee, array $settings, array $brackets, ?string $asOf = null): array
{
    $basic = num($employee['basic_pay']);
    $gross = grossPay($employee);
    $gsis = round($basic * $settings['gsis_rate'], 2);
    $phic = round($basic * $settings['phic_rate'], 2);
    $pagibig = round($settings['pagibig'], 2);

    $taxable = $gross - ($gsis + $phic + $pagibig);
    $tax = computeTax($taxable, $brackets);

    $mandatory = $gsis + $phic + $pagibig + $tax;
    $running = $mandatory;
    $applied = [];
    $excluded = [];

    foreach (employeeDeductions((int) $employee['id'], $asOf) as $deduction) {
        $amount = num($deduction['amount']);
        $projected = $gross - ($running + $amount);
        if ($projected < $settings['net_floor']) {
            $excluded[] = $deduction + ['amount' => $amount, 'reason' => 'net pay would fall below the ' . peso($settings['net_floor']) . ' floor'];
            continue;
        }
        $running += $amount;
        $applied[] = $deduction + ['amount' => $amount];
    }

    $otherTotal = array_sum(array_column($applied, 'amount'));

    return [
        'gross_pay' => round($gross, 2),
        'gsis' => $gsis,
        'philhealth' => $phic,
        'pagibig' => $pagibig,
        'tax' => $tax,
        'basic_pay' => $basic,
        'pera' => num($employee['pera']),
        'trans_allow' => num($employee['trans_allow']),
        'aca' => num($employee['aca']),
        'rata' => num($employee['rata']),
        'ontop' => num($employee['ontop']),
        'long_pay' => num($employee['long_pay']),
        'other_deductions' => round($otherTotal, 2),
        'total_deductions' => round($running, 2),
        'net_pay' => round($gross - $running, 2),
        'taxable' => round($taxable, 2),
        'applied' => $applied,
        'excluded' => $excluded,
    ];
}

function payrollPeriod(int $id): array|false
{
    return find('SELECT * FROM `payroll_periods` WHERE `id` = ? LIMIT 1', [$id]);
}

function payrollItems(int $periodId): array
{
    $rows = query(
        'SELECT i.*, e.`empno`, e.`last_name`, e.`first_name`, e.`middle_name`, e.`station_code`, e.`position`
         FROM `payroll_items` i
         INNER JOIN `employees` e ON e.`id` = i.`employee_id`
         WHERE i.`period_id` = ?
         ORDER BY e.`last_name` ASC, e.`first_name` ASC',
        [$periodId]
    );
    return is_array($rows) ? $rows : [];
}

function payrollTotals(int $periodId): array
{
    $row = find(
        'SELECT COUNT(*) AS `headcount`, COALESCE(SUM(`gross_pay`), 0) AS `gross`,
                COALESCE(SUM(`gsis` + `philhealth` + `pagibig` + `tax` + `other_deductions`), 0) AS `deductions`,
                COALESCE(SUM(`net_pay`), 0) AS `net`
         FROM `payroll_items` WHERE `period_id` = ?',
        [$periodId]
    );
    return $row ?: ['headcount' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];
}

function periodLabel(array $period): string
{
    $from = date('M j, Y', strtotime($period['period_from']));
    $to = date('M j, Y', strtotime($period['period_to']));
    return ucfirst($period['type']) . ' · ' . $from . ' – ' . $to;
}

/**
 * Generate (or regenerate) the payroll items of a draft period.
 * Mirrors PAYRSTN -> PAYRINCL -> PAYRMRG -> PAYEND of the legacy system.
 */
function generatePayroll(int $periodId): array
{
    $period = payrollPeriod($periodId);
    if (!$period) {
        return ['ok' => false, 'message' => 'Payroll period not found.'];
    }
    if ($period['status'] !== 'draft') {
        return ['ok' => false, 'message' => 'Only draft periods can be regenerated.'];
    }

    $employees = query('SELECT * FROM `employees` WHERE `archived` = 0 ORDER BY `last_name` ASC, `first_name` ASC');
    if (!is_array($employees) || $employees === []) {
        return ['ok' => false, 'message' => 'There are no active employees to pay.'];
    }

    $settings = payrollSettings();
    $brackets = taxBrackets();
    $asOf = $period['period_to'];

    $db = connection();
    $db->beginTransaction();
    try {
        delete('payroll_deduction_items', '`payroll_item_id` IN (SELECT `id` FROM `payroll_items` WHERE `period_id` = ?)', [$periodId]);
        delete('payroll_items', '`period_id` = ?', [$periodId]);

        $count = 0;
        foreach ($employees as $employee) {
            $line = computePayrollLine($employee, $settings, $brackets, $asOf);
            $itemId = insert('payroll_items', [
                'period_id' => $periodId,
                'employee_id' => (int) $employee['id'],
                'basic_pay' => $line['basic_pay'],
                'pera' => $line['pera'],
                'trans_allow' => $line['trans_allow'],
                'aca' => $line['aca'],
                'rata' => $line['rata'],
                'ontop' => $line['ontop'],
                'long_pay' => $line['long_pay'],
                'gross_pay' => $line['gross_pay'],
                'gsis' => $line['gsis'],
                'philhealth' => $line['philhealth'],
                'pagibig' => $line['pagibig'],
                'tax' => $line['tax'],
                'other_deductions' => $line['other_deductions'],
                'total_deductions' => $line['total_deductions'],
                'net_pay' => $line['net_pay'],
            ]);
            if (!$itemId) {
                throw new RuntimeException('Unable to save a payroll line.');
            }

            foreach ($line['applied'] as $applied) {
                insert('payroll_deduction_items', [
                    'payroll_item_id' => $itemId,
                    'deduction_type_id' => (int) $applied['deduction_type_id'],
                    'label' => $applied['name'],
                    'amount' => $applied['amount'],
                    'included' => 1,
                    'reason' => null,
                ]);
            }
            foreach ($line['excluded'] as $excluded) {
                insert('payroll_deduction_items', [
                    'payroll_item_id' => $itemId,
                    'deduction_type_id' => (int) $excluded['deduction_type_id'],
                    'label' => $excluded['name'],
                    'amount' => $excluded['amount'],
                    'included' => 0,
                    'reason' => $excluded['reason'],
                ]);
            }
            $count++;
        }

        update('payroll_periods', ['generated_at' => date('Y-m-d H:i:s')], '`id` = ?', [$periodId]);
        $db->commit();
        return ['ok' => true, 'message' => "Payroll generated for {$count} employee(s)."];
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('DIPEE payroll generation failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Payroll generation failed: ' . $e->getMessage()];
    }
}

function payrollDeductionItems(int $itemId): array
{
    $rows = query('SELECT * FROM `payroll_deduction_items` WHERE `payroll_item_id` = ? ORDER BY `included` DESC, `label` ASC', [$itemId]);
    return is_array($rows) ? $rows : [];
}
