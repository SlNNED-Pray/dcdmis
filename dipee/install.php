<?php
// dipee/install.php - creates the payroll database, tables and demo data.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $server = new PDO(sprintf('mysql:host=%s;charset=utf8mb4', PAYROLL_HOST), PAYROLL_USER, PAYROLL_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . PAYROLL_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $db = connection();
        if (!$db) {
            throw new RuntimeException('Could not connect to the payroll database.');
        }

        $tables = [
            'CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(60) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `full_name` VARCHAR(120) NOT NULL,
                `role` VARCHAR(30) NOT NULL DEFAULT \'admin\',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `settings` (
                `skey` VARCHAR(60) PRIMARY KEY,
                `svalue` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `stations` (
                `code` VARCHAR(3) PRIMARY KEY,
                `name` VARCHAR(120) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `employees` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `empno` VARCHAR(12) NOT NULL UNIQUE,
                `last_name` VARCHAR(60) NOT NULL,
                `first_name` VARCHAR(60) NOT NULL,
                `middle_name` VARCHAR(60) NULL,
                `station_code` VARCHAR(3) NULL,
                `position` VARCHAR(120) NULL,
                `grade` VARCHAR(4) NULL,
                `step` VARCHAR(4) NULL,
                `basic_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `pera` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `trans_allow` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `aca` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `rata` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `ontop` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `long_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `taxcode` VARCHAR(2) NULL,
                `status` VARCHAR(2) NOT NULL DEFAULT \'P\',
                `paymode` CHAR(1) NOT NULL DEFAULT \'A\',
                `bank` VARCHAR(60) NULL,
                `account_no` VARCHAR(20) NULL,
                `gsis_pn` VARCHAR(20) NULL,
                `phic_id` VARCHAR(20) NULL,
                `hdmf_id` VARCHAR(20) NULL,
                `tin` VARCHAR(20) NULL,
                `hire_date` DATE NULL,
                `archived` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `deduction_types` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(5) NOT NULL UNIQUE,
                `name` VARCHAR(120) NOT NULL,
                `kind` VARCHAR(10) NOT NULL DEFAULT \'loan\',
                `agency` VARCHAR(30) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `employee_deductions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `employee_id` INT UNSIGNED NOT NULL,
                `deduction_type_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `policy_no` VARCHAR(24) NULL,
                `effective_from` DATE NULL,
                `effective_to` DATE NULL,
                `priority` INT NOT NULL DEFAULT 1,
                `status` CHAR(1) NOT NULL DEFAULT \'A\',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `employee_id` (`employee_id`),
                CONSTRAINT `fk_deduction_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_deduction_type` FOREIGN KEY (`deduction_type_id`) REFERENCES `deduction_types` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `tax_brackets` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `label` VARCHAR(60) NOT NULL,
                `lower_bound` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `upper_bound` DECIMAL(12,2) NULL,
                `base_tax` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `rate` DECIMAL(6,4) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `payroll_periods` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `type` VARCHAR(15) NOT NULL DEFAULT \'regular\',
                `period_from` DATE NOT NULL,
                `period_to` DATE NOT NULL,
                `pay_date` DATE NOT NULL,
                `status` VARCHAR(10) NOT NULL DEFAULT \'draft\',
                `notes` VARCHAR(255) NULL,
                `generated_at` DATETIME NULL,
                `finalized_at` DATETIME NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `payroll_items` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `period_id` INT UNSIGNED NOT NULL,
                `employee_id` INT UNSIGNED NOT NULL,
                `basic_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `pera` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `trans_allow` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `aca` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `rata` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `ontop` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `long_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `gross_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `gsis` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `philhealth` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `pagibig` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `tax` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `other_deductions` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `total_deductions` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `net_pay` DECIMAL(14,2) NOT NULL DEFAULT 0,
                UNIQUE KEY `period_employee` (`period_id`, `employee_id`),
                CONSTRAINT `fk_item_period` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_item_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `payroll_deduction_items` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `payroll_item_id` INT UNSIGNED NOT NULL,
                `deduction_type_id` INT UNSIGNED NULL,
                `label` VARCHAR(120) NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `included` TINYINT(1) NOT NULL DEFAULT 1,
                `reason` VARCHAR(180) NULL,
                KEY `payroll_item_id` (`payroll_item_id`),
                CONSTRAINT `fk_deduction_item` FOREIGN KEY (`payroll_item_id`) REFERENCES `payroll_items` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($tables as $sql) {
            $db->exec($sql);
        }
        $messages[] = 'Database schema is ready.';

        if (!find('SELECT `id` FROM `users` LIMIT 1')) {
            insert('users', [
                'username' => 'admin',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'full_name' => 'System Administrator',
                'role' => 'admin',
            ]);
            $messages[] = 'Administrator account created (admin / admin123). Change it after signing in.';
        }

        $defaults = [
            'org_name' => 'Department of Education',
            'division' => 'SCHOOLS DIVISION OF DIPOLOG CITY',
            'region' => 'REGION IX - ZAMBOANGA PENINSULA',
            'payslip_note' => 'This payslip is system generated. Keep it for your records.',
            'gsis_rate' => '0.09',
            'phic_rate' => '0.025',
            'pagibig_amount' => '200',
            'net_floor' => '5000',
        ];
        foreach ($defaults as $key => $value) {
            if (!find('SELECT `skey` FROM `settings` WHERE `skey` = ?', [$key])) {
                setSetting($key, $value);
            }
        }

        if (!find('SELECT `id` FROM `tax_brackets` LIMIT 1')) {
            $brackets = [
                ['None', 0, 20833, 0, 0],
                ['15% of excess over 20,833', 20833, 33333, 0, 0.15],
                ['1,875 + 20% of excess over 33,333', 33333, 66667, 1875, 0.20],
                ['8,541.80 + 25% of excess over 66,667', 66667, 166667, 8541.80, 0.25],
                ['33,541.80 + 30% of excess over 166,667', 166667, 666667, 33541.80, 0.30],
                ['183,541.80 + 35% of excess over 666,667', 666667, null, 183541.80, 0.35],
            ];
            foreach ($brackets as $i => [$label, $lower, $upper, $base, $rate]) {
                insert('tax_brackets', [
                    'label' => $label,
                    'lower_bound' => $lower,
                    'upper_bound' => $upper,
                    'base_tax' => $base,
                    'rate' => $rate,
                    'sort_order' => $i + 1,
                ]);
            }
            $messages[] = 'Tax brackets seeded from PAYTAX.PRG.';
        }

        if (!find('SELECT `code` FROM `stations` LIMIT 1')) {
            foreach ([
                ['001', 'Dipolog City North District'],
                ['002', 'Dipolog City East District'],
                ['003', 'Dipolog City West District'],
                ['004', 'Division Office'],
                ['005', 'Dipolog City South District'],
            ] as [$code, $name]) {
                insert('stations', ['code' => $code, 'name' => $name]);
            }
            $messages[] = 'Stations seeded.';
        }

        if (!find('SELECT `id` FROM `deduction_types` LIMIT 1')) {
            foreach ([
                ['0001', 'GSIS Policy Loan', 'loan', 'GSIS'],
                ['0002', 'GSIS Salary Loan', 'loan', 'GSIS'],
                ['0007', 'GSIS Emergency Loan', 'loan', 'GSIS'],
                ['0100', 'Pag-IBIG Multi-Purpose Loan', 'loan', 'HDMF'],
                ['0200', 'PhilHealth Premium Loan', 'loan', 'PHIC'],
                ['0300', 'Other Deduction', 'other', null],
            ] as [$code, $name, $kind, $agency]) {
                insert('deduction_types', ['code' => $code, 'name' => $name, 'kind' => $kind, 'agency' => $agency, 'active' => 1]);
            }
            $messages[] = 'Deduction types seeded.';
        }

        if (!find('SELECT `id` FROM `employees` LIMIT 1')) {
            $demo = [
                ['1000001', 'DELA CRUZ', 'Maria', 'Santos', '001', 'Teacher III', '13', '1', 33183, 2000],
                ['1000002', 'REYES', 'Juan', 'Bautista', '002', 'Teacher I', '11', '1', 30024, 2000],
                ['1000003', 'GARCIA', 'Ana', 'Lopez', '003', 'Master Teacher I', '18', '1', 51157, 2000],
                ['1000004', 'VILLANUEVA', 'Pedro', 'Ramos', '004', 'Administrative Officer II', '11', '1', 30024, 2000],
                ['1000005', 'TORRES', 'Liza', 'Mendoza', '001', 'Teacher II', '12', '2', 31633, 2000],
                ['1000006', 'NAVARRO', 'Ramon', 'Cruz', '005', 'Principal I', '19', '1', 53873, 2000],
            ];
            foreach ($demo as [$empno, $last, $first, $middle, $station, $position, $grade, $step, $basic, $pera]) {
                insert('employees', [
                    'empno' => $empno,
                    'last_name' => $last,
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'station_code' => $station,
                    'position' => $position,
                    'grade' => $grade,
                    'step' => $step,
                    'basic_pay' => $basic,
                    'pera' => $pera,
                    'taxcode' => '03',
                    'status' => 'P',
                    'paymode' => 'A',
                    'hire_date' => '2015-06-01',
                ]);
            }

            $types = query('SELECT `id`, `code` FROM `deduction_types`');
            $byCode = [];
            foreach ($types as $type) {
                $byCode[$type['code']] = (int) $type['id'];
            }
            $loans = [
                [1, '0001', 150.00, 1],
                [1, '0007', 1933.28, 2],
                [2, '0002', 2000.00, 1],
                [3, '0001', 500.00, 1],
                [4, '0007', 1200.00, 1],
                [5, '0100', 800.00, 1],
                [6, '0002', 2500.00, 1],
            ];
            foreach ($loans as [$employeeId, $code, $amount, $priority]) {
                if (!isset($byCode[$code])) {
                    continue;
                }
                insert('employee_deductions', [
                    'employee_id' => $employeeId,
                    'deduction_type_id' => $byCode[$code],
                    'amount' => $amount,
                    'priority' => $priority,
                    'status' => 'A',
                ]);
            }
            $messages[] = 'Six demo employees with loan deductions created. Use Import to load the real legacy data.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$ready = installed();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install · <?= e(PAYROLL_TITLE) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page">
<main class="login-card install-card">
    <h1><?= e(PAYROLL_TITLE) ?></h1>
    <p class="muted"><?= e(PAYROLL_SUBTITLE) ?></p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endforeach; ?>

    <?php if ($ready && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <p>The payroll database is already installed.</p>
        <a class="btn btn-primary" href="index.php">Open the payroll system</a>
    <?php else: ?>
        <p>This will create the <code><?= e(PAYROLL_DB) ?></code> database with its tables,
            an administrator account and demo data. Existing payroll tables are left untouched.</p>
        <form method="post">
            <button class="btn btn-primary" type="submit">Install / verify database</button>
        </form>
        <?php if ($ready): ?>
            <p><a href="index.php">Go to sign in</a></p>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
