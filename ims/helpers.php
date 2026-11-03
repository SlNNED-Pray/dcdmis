<?php
require_once(__DIR__ . '/../includes/database/account.php');

/**
 * Whether the signed-in account is IMS staff (holds the 'ims' role).
 *
 * Staff get the full Inventory menu and may manage/approve stock documents;
 * everyone else only gets Requisition Slips.
 */
function imsIsStaff(): bool
{
    $userId = $GLOBALS['userId'] ?? null;
    if (empty($userId)) {
        return false;
    }

    return (bool) userRole($userId, 'ims');
}

/**
 * Abort the request with a 403 unless the signed-in account is IMS staff.
 *
 * Print and save endpoints are requested directly (outside the IMS module
 * bootstrap), so they call this guard to confirm the visitor may manage
 * inventory documents. JSON endpoints receive a JSON error, everything else
 * gets a small HTML notice.
 */
function requireImsStaff(): void
{
    if (imsIsStaff()) {
        return;
    }

    http_response_code(403);

    $wantsJson = stripos(implode('|', headers_list()), 'application/json') !== false
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'FORBIDDEN',
            'message' => 'You are not authorized to perform this action.',
        ]);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access Denied</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:60px;">'
        . '<h1 style="font-size:22px;">Access Denied</h1>'
        . '<p>You are not authorized to view this document.</p>'
        . '<p><a href="javascript:window.close()">Close window</a></p></body></html>';
    exit;
}

function imsItemCategory(string $description, string $stockNo = ''): string
{
    $haystack = ' ' . strtolower($description . ' ' . $stockNo) . ' ';
    $groups = [
        'ICT Equipment' => ['laptop', 'computer', 'desktop', 'printer', 'scanner', 'monitor', 'keyboard', 'mouse', 'toner', 'cartridge', 'software', 'projector', 'router', 'network', 'hard drive', 'flash drive', 'ups', 'server', 'modem', 'webcam', 'headset', 'tablet', 'photocopier', 'plotter', 'switch'],
        'Medical / First Aid' => ['alcohol', 'bandage', 'gauze', 'medicine', 'thermometer', 'syringe', 'cotton', 'plaster', 'first aid', 'antiseptic', 'betadine'],
        'Janitorial Supplies' => ['broom', 'mop', 'detergent', 'soap', 'cleanser', 'disinfectant', 'air freshener', 'toilet', 'toilet tissue', 'tissue paper', 'paper towel', 'tissue holder', 'garbage', 'trash', 'insecticide', 'wax', 'sponge', 'brush', 'dustpan', 'deodorizer', 'sanitizer', 'bleach', 'floor', 'rags'],
        'Office Supplies' => ['pen', 'ballpoint', 'pencil', 'paper', 'bond', 'folder', 'envelope', 'staple', 'stapler', 'clip', 'tape', 'glue', 'notepad', 'notebook', 'eraser', 'sharpener', 'ruler', 'scissors', 'marker', 'chalk', 'crayon', 'binder', 'clearbook', 'logbook', 'certificate', 'cartolina', 'fastener', 'thumbtack', 'rubber band', 'stamp', 'correction', 'highlighter', 'index card', 'record book', 'paste', 'puncher', 'cutter', 'blade', 'agenda', 'planner', 'ink'],
        'Electrical / Hardware' => ['fan', 'bulb', 'lamp', 'extension', 'wire', 'battery', 'outlet', 'plug', 'fluorescent', 'led', 'electrical', 'cable'],
        'Furniture' => ['table', 'chair', 'desk', 'cabinet', 'shelf', 'locker', 'bed', 'rack'],
    ];

    foreach ($groups as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '(s|es)?\b/', $haystack)) {
                return $category;
            }
        }
    }

    return 'Other';
}

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
