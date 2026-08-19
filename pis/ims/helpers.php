<?php
function imsMoney($amount): string
{
    return number_format((float) $amount, 2);
}

function imsNextDocumentNumber(string $prefix, string $table, string $column): string
{
    $statement = connection()->prepare(
        "SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $statement->execute(["{$prefix}-%"]);
    $lastNumber = $statement->fetchColumn();
    $sequence = $lastNumber ? ((int) substr((string) $lastNumber, strrpos((string) $lastNumber, '-') + 1) + 1) : 1;

    return sprintf('%s-%04d', $prefix, $sequence);
}