<?php
// dipee/db.php - PDO access layer, follows includes/database/database.php conventions.

function connection(): ?PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', PAYROLL_HOST, PAYROLL_DB);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return $pdo = new PDO($dsn, PAYROLL_USER, PAYROLL_PASSWORD, $options);
    } catch (PDOException $e) {
        error_log('DIPEE connection error: ' . $e->getMessage());
        return null;
    }
}

function query(string $sql, array $params = []): array|int|false
{
    $db = connection();
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->columnCount() > 0) {
            return $stmt->fetchAll();
        }
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('DIPEE query error: ' . $e->getMessage() . ' | ' . $sql);
        return false;
    }
}

function find(string $sql, array $params = []): array|false
{
    $rows = query($sql, $params);
    return is_array($rows) && isset($rows[0]) ? $rows[0] : false;
}

function execute(string $sql, array $params = []): bool
{
    $db = connection();
    if (!$db) {
        return false;
    }
    try {
        return $db->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        error_log('DIPEE execute error: ' . $e->getMessage() . ' | ' . $sql);
        return false;
    }
}

function insert(string $table, array $data): int|false
{
    $columns = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $db = connection();
    if (!$db || !execute($sql, array_values($data))) {
        return false;
    }
    return (int) $db->lastInsertId();
}

function update(string $table, array $data, string $where, array $whereParams = []): bool
{
    if (empty($data)) {
        return false;
    }
    $sets = implode(' = ?, ', array_map(fn ($c) => "`{$c}`", array_keys($data))) . ' = ?';
    $sql = "UPDATE `{$table}` SET {$sets} WHERE {$where}";
    return execute($sql, [...array_values($data), ...$whereParams]);
}

function delete(string $table, string $where, array $params = []): bool
{
    return execute("DELETE FROM `{$table}` WHERE {$where}", $params);
}

function installed(): bool
{
    $db = connection();
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT 1 FROM `users` LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
