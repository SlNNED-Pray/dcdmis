<?php
// dipee/views/settings.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save-settings'])) {
        foreach (['org_name', 'division', 'region', 'payslip_note'] as $key) {
            setSetting($key, trim((string) ($_POST[$key] ?? '')));
        }
        setSetting('gsis_rate', (string) max(0, min(1, num($_POST['gsis_rate'] ?? 0) / 100)));
        setSetting('phic_rate', (string) max(0, min(1, num($_POST['phic_rate'] ?? 0) / 100)));
        setSetting('pagibig_amount', (string) max(0, num($_POST['pagibig_amount'] ?? 0)));
        setSetting('net_floor', (string) max(0, num($_POST['net_floor'] ?? 0)));
        flash('success', 'Settings saved.');
        redirect('index.php?page=settings');
    }

    if (isset($_POST['save-brackets'])) {
        $ids = $_POST['bracket_id'] ?? [];
        $lowers = $_POST['lower_bound'] ?? [];
        $uppers = $_POST['upper_bound'] ?? [];
        $bases = $_POST['base_tax'] ?? [];
        $rates = $_POST['rate'] ?? [];
        foreach ($ids as $i => $bracketId) {
            $upper = trim((string) ($uppers[$i] ?? ''));
            update('tax_brackets', [
                'lower_bound' => num($lowers[$i] ?? 0),
                'upper_bound' => $upper === '' ? null : num($upper),
                'base_tax' => num($bases[$i] ?? 0),
                'rate' => max(0, min(1, num($rates[$i] ?? 0) / 100)),
            ], '`id` = ?', [(int) $bracketId]);
        }
        flash('success', 'Tax brackets saved.');
        redirect('index.php?page=settings');
    }

    if (isset($_POST['add-station'])) {
        $code = trim((string) ($_POST['station_code'] ?? ''));
        $name = trim((string) ($_POST['station_name'] ?? ''));
        if ($code === '' || $name === '') {
            flash('error', 'Station code and name are required.');
        } elseif (find('SELECT `code` FROM `stations` WHERE `code` = ?', [$code])) {
            flash('error', 'That station code already exists.');
        } else {
            insert('stations', ['code' => $code, 'name' => $name]);
            flash('success', 'Station added.');
        }
        redirect('index.php?page=settings');
    }

    if (isset($_POST['delete-station'])) {
        $code = (string) ($_POST['station_code'] ?? '');
        if (find('SELECT `id` FROM `employees` WHERE `station_code` = ? LIMIT 1', [$code])) {
            flash('error', 'That station is still assigned to employees.');
        } else {
            delete('stations', '`code` = ?', [$code]);
            flash('success', 'Station removed.');
        }
        redirect('index.php?page=settings');
    }

    if (isset($_POST['change-password'])) {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            flash('error', 'The current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } else {
            update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], '`id` = ?', [(int) $user['id']]);
            flash('success', 'Password changed.');
        }
        redirect('index.php?page=settings');
    }
}

$settings = payrollSettings();
$brackets = taxBrackets();
$stationRows = stations();
?>
<div class="page-head">
    <div>
        <h1>Settings</h1>
        <p class="muted">Organisation details, contribution rates and tax brackets</p>
    </div>
</div>

<div class="grid-2 align-start">
    <section class="card">
        <h2>Organisation</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <label>Organisation <input type="text" name="org_name" value="<?= e(getSetting('org_name', 'Department of Education')) ?>"></label>
            <label>Region <input type="text" name="region" value="<?= e(getSetting('region')) ?>"></label>
            <label>Division <input type="text" name="division" value="<?= e(getSetting('division')) ?>"></label>
            <label>Payslip footnote <input type="text" name="payslip_note" value="<?= e(getSetting('payslip_note')) ?>"></label>

            <h3>Contribution rates</h3>
            <div class="form-grid">
                <label>GSIS % of basic <input type="number" step="0.01" min="0" max="100" name="gsis_rate" value="<?= e($settings['gsis_rate'] * 100) ?>"></label>
                <label>PhilHealth % of basic <input type="number" step="0.01" min="0" max="100" name="phic_rate" value="<?= e($settings['phic_rate'] * 100) ?>"></label>
                <label>Pag-IBIG amount <input type="number" step="0.01" min="0" name="pagibig_amount" value="<?= e($settings['pagibig']) ?>"></label>
                <label>Net pay floor <input type="number" step="0.01" min="0" name="net_floor" value="<?= e($settings['net_floor']) ?>"></label>
            </div>
            <button class="btn btn-primary" type="submit" name="save-settings" value="1">Save settings</button>
        </form>
    </section>

    <section class="card">
        <h2>Change password</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <label>Current password <input type="password" name="current_password" required></label>
            <label>New password <input type="password" name="new_password" minlength="8" required></label>
            <button class="btn btn-primary" type="submit" name="change-password" value="1">Update password</button>
        </form>

        <h2>Stations</h2>
        <table class="table table-compact">
            <thead><tr><th>Code</th><th>Name</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($stationRows as $station): ?>
                <tr>
                    <td class="mono"><?= e($station['code']) ?></td>
                    <td><?= e($station['name']) ?></td>
                    <td class="right">
                        <form method="post" class="inline" onsubmit="return confirm('Remove this station?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="station_code" value="<?= e($station['code']) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit" name="delete-station" value="1">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <label>Code <input type="text" name="station_code" maxlength="3" required></label>
            <label class="grow">Name <input type="text" name="station_name" required></label>
            <button class="btn btn-ghost" type="submit" name="add-station" value="1">Add station</button>
        </form>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <h2>Withholding tax brackets (monthly)</h2>
        <span class="muted small">Seeded from the brackets coded in PAYTAX.PRG</span>
    </div>
    <form method="post">
        <?= csrfField() ?>
        <table class="table table-compact">
            <thead>
                <tr><th>Bracket</th><th class="num">Lower bound</th><th class="num">Upper bound</th>
                    <th class="num">Base tax</th><th class="num">Rate %</th></tr>
            </thead>
            <tbody>
            <?php foreach ($brackets as $bracket): ?>
                <tr>
                    <td>
                        <?= e($bracket['label']) ?>
                        <input type="hidden" name="bracket_id[]" value="<?= (int) $bracket['id'] ?>">
                    </td>
                    <td><input class="money" type="number" step="0.01" name="lower_bound[]" value="<?= e($bracket['lower_bound']) ?>"></td>
                    <td><input class="money" type="number" step="0.01" name="upper_bound[]" value="<?= e($bracket['upper_bound']) ?>" placeholder="none"></td>
                    <td><input class="money" type="number" step="0.01" name="base_tax[]" value="<?= e($bracket['base_tax']) ?>"></td>
                    <td><input class="money" type="number" step="0.001" name="rate[]" value="<?= e($bracket['rate'] * 100) ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button class="btn btn-primary" type="submit" name="save-brackets" value="1">Save brackets</button>
    </form>
</section>
