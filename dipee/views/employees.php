<?php
// dipee/views/employees.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle-archive'])) {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $emp = employee($id);
    if ($emp) {
        update('employees', ['archived' => $emp['archived'] ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')], '`id` = ?', [$id]);
        flash('success', employeeName($emp) . ' has been ' . ($emp['archived'] ? 'restored' : 'archived') . '.');
    }
    redirect('index.php?page=employees');
}

$search = trim((string) ($_GET['q'] ?? ''));
$station = trim((string) ($_GET['station'] ?? ''));
$showArchived = isset($_GET['archived']);

$sql = 'SELECT * FROM `employees` WHERE 1 = 1';
$params = [];
if ($showArchived) {
    $sql .= ' AND `archived` = 1';
} else {
    $sql .= ' AND `archived` = 0';
}
if ($search !== '') {
    $sql .= ' AND (`empno` LIKE ? OR `last_name` LIKE ? OR `first_name` LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($station !== '') {
    $sql .= ' AND `station_code` = ?';
    $params[] = $station;
}
$sql .= ' ORDER BY `last_name` ASC, `first_name` ASC';
$employees = query($sql, $params);
$employees = is_array($employees) ? $employees : [];
?>
<div class="page-head">
    <div>
        <h1>Employees</h1>
        <p class="muted">Master records from the legacy MASTFILE table</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="index.php?page=employee-form">Add employee</a>
    </div>
</div>

<section class="card">
    <form class="filters" method="get">
        <input type="hidden" name="page" value="employees">
        <label class="grow">
            Search
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Employee number or name">
        </label>
        <label>
            Station
            <select name="station">
                <option value="">All stations</option>
                <?php foreach (stations() as $row): ?>
                    <option value="<?= e($row['code']) ?>" <?= $station === $row['code'] ? 'selected' : '' ?>>
                        <?= e($row['code'] . ' · ' . $row['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="archived" value="1" <?= $showArchived ? 'checked' : '' ?>>
            Archived only
        </label>
        <button class="btn btn-ghost" type="submit">Filter</button>
    </form>

    <?php if ($employees === []): ?>
        <p class="empty">No employees match this filter. <a href="index.php?page=employee-form">Add one</a>
            or <a href="index.php?page=import">import the legacy MASTFILE</a>.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee no.</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Station</th>
                    <th class="num">Basic pay</th>
                    <th class="num">Monthly gross</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['empno']) ?></td>
                    <td>
                        <a href="index.php?page=employee-form&amp;id=<?= (int) $row['id'] ?>"><?= e(employeeName($row)) ?></a>
                        <?php if ($row['archived']): ?><span class="badge badge-draft">Archived</span><?php endif; ?>
                    </td>
                    <td><?= e($row['position']) ?></td>
                    <td><?= e(stationName($row['station_code'])) ?></td>
                    <td class="num"><?= peso($row['basic_pay']) ?></td>
                    <td class="num"><?= peso(grossPay($row)) ?></td>
                    <td class="right nowrap">
                        <a class="btn btn-ghost btn-sm" href="index.php?page=deductions&amp;employee_id=<?= (int) $row['id'] ?>">Loans</a>
                        <form method="post" class="inline" onsubmit="return confirm('Change the archive status of this employee?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit" name="toggle-archive" value="1">
                                <?= $row['archived'] ? 'Restore' : 'Archive' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted small"><?= e(number_format(count($employees))) ?> record(s)</p>
    <?php endif; ?>
</section>
