<?php
// dipee/lib/dbf.php - minimal read-only dBase III reader for the legacy DIPEE tables.
// Supports the C, N, D and L field types used by the FoxPro 2.x files.

class LegacyDbf
{
    private $handle;
    private array $fields = [];
    private int $recordCount;
    private int $recordLength;
    private int $headerLength;
    private string $encoding;

    public function __construct(string $path, string $encoding = 'CP437')
    {
        if (!is_readable($path)) {
            throw new RuntimeException('Cannot read legacy table: ' . $path);
        }
        $this->encoding = $encoding;
        $this->handle = fopen($path, 'rb');
        $header = fread($this->handle, 32);
        if (strlen($header) < 32) {
            throw new RuntimeException('Not a DBF file: ' . $path);
        }
        $this->recordCount = unpack('V', substr($header, 4, 4))[1];
        $this->headerLength = unpack('v', substr($header, 8, 2))[1];
        $this->recordLength = unpack('v', substr($header, 10, 2))[1];

        $offset = 1;
        while (($descriptor = fread($this->handle, 32)) !== false && strlen($descriptor) === 32 && ord($descriptor[0]) !== 0x0D) {
            $length = ord($descriptor[16]);
            $this->fields[] = [
                'name' => rtrim(substr($descriptor, 0, 11), "\0 "),
                'type' => $descriptor[11],
                'length' => $length,
                'decimals' => ord($descriptor[17]),
                'offset' => $offset,
            ];
            $offset += $length;
        }
    }

    public function fieldNames(): array
    {
        return array_column($this->fields, 'name');
    }

    public function count(): int
    {
        return $this->recordCount;
    }

    // Yields associative rows; deleted records are skipped.
    public function rows(): Generator
    {
        fseek($this->handle, $this->headerLength);
        for ($i = 0; $i < $this->recordCount; $i++) {
            $record = fread($this->handle, $this->recordLength);
            if ($record === false || strlen($record) < $this->recordLength) {
                break;
            }
            if ($record[0] === '*') {
                continue;
            }
            $row = [];
            foreach ($this->fields as $field) {
                $raw = substr($record, $field['offset'], $field['length']);
                $row[$field['name']] = $this->decode($raw, $field['type']);
            }
            yield $row;
        }
    }

    public function first(): ?array
    {
        foreach ($this->rows() as $row) {
            return $row;
        }
        return null;
    }

    private function decode(string $raw, string $type): mixed
    {
        $text = rtrim($raw, "\0 ");
        if ($type === 'N' || $type === 'F') {
            $text = trim($text);
            if ($text === '' || $text === '.' || $text === '-') {
                return 0.0;
            }
            return (float) str_replace(',', '', $text);
        }
        if ($type === 'D') {
            $text = trim($text);
            return strlen($text) === 8 ? substr($text, 0, 4) . '-' . substr($text, 4, 2) . '-' . substr($text, 6, 2) : null;
        }
        if ($type === 'L') {
            return in_array($text, ['T', 't', 'Y', 'y'], true);
        }
        $text = trim($text);
        return $text === '' ? null : $this->toUtf8($text);
    }

    private function toUtf8(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv($this->encoding, 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                return trim($converted);
            }
        }
        return trim(mb_convert_encoding($text, 'UTF-8', $this->encoding));
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
