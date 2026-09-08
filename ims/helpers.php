<?php
function imsNextDocumentNumber(string $prefix, string $table, string $column): string
{
    $statement = connection()->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1");
    $statement->execute(["{$prefix}-%"]);
    $lastNumber = $statement->fetchColumn();
    $sequence = $lastNumber ? ((int) substr((string) $lastNumber, strrpos((string) $lastNumber, '-') + 1) + 1) : 1;

    return sprintf('%s-%04d', $prefix, $sequence);
}

function imsRecordCostHistory(int $itemId, float $newCost, float $oldCost = 0.0, string $reference = 'Manual Edit', ?string $remarks = null, ?string $priceDate = null, ?int $userId = null): bool
{
    if (round((float) $oldCost, 2) === round((float) $newCost, 2)) {
        return false;
    }
    $result = insert('item_cost_history', [
        'item_id' => $itemId,
        'price_date' => $priceDate ?: date('Y-m-d'),
        'old_cost' => round((float) $oldCost, 2),
        'new_cost' => round((float) $newCost, 2),
        'reference' => $reference,
        'remarks' => $remarks,
        'created_by' => $userId,
    ]);
    return $result !== false;
}

function risDivisionOfficeFromEmployee(int $employeeId): array
{
    $station = station($employeeId);
    if (!$station || empty($station['station_id'])) {
        return ['division' => '', 'office' => ''];
    }
    $stationId = (string) $station['station_id'];

    $division = '';
    $section = find('SELECT id, name, functional_division_id FROM sections WHERE id = ?', [$stationId]);
    if ($section) {
        if (!empty($section['functional_division_id'])) {
            $div = find('SELECT name FROM functional_divisions WHERE id = ?', [$section['functional_division_id']]);
            $division = $div ? (string) $div['name'] : '';
        }
        $office = (string) $section['name'];
    } else {
        $office = stationName($stationId);
    }

    return ['division' => $division, 'office' => $office];
}

function poNumberToWords(float $amount): string
{
    $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
    $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
    $whole = (int) floor($amount);
    $convert = static function (int $number) use (&$convert, $ones, $tens): string {
        if ($number < 20) return $ones[$number];
        if ($number < 100) return $tens[intdiv($number, 10)] . ($number % 10 ? '-' . $ones[$number % 10] : '');
        if ($number < 1000) return $ones[intdiv($number, 100)] . ' HUNDRED' . ($number % 100 ? ' ' . $convert($number % 100) : '');
        if ($number < 1000000) return $convert(intdiv($number, 1000)) . ' THOUSAND' . ($number % 1000 ? ' ' . $convert($number % 1000) : '');
        return $convert(intdiv($number, 1000000)) . ' MILLION' . ($number % 1000000 ? ' ' . $convert($number % 1000000) : '');
    };
    $centavos = (int) round(($amount - $whole) * 100);
    return ($whole ? $convert($whole) : 'ZERO') . ' PESOS' . ($centavos ? ' AND ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100' : ' ONLY');
}
