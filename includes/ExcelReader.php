<?php
/**
 * Lightweight XLSX reader — no Composer dependency.
 * Uses PHP's built-in ZipArchive + SimpleXML.
 * Handles shared strings and basic cell types.
 */
class ExcelReader {

    private ZipArchive $zip;
    private array $sharedStrings = [];
    private array $sheets = [];

    public function __construct(string $filePath) {
        $this->zip = new ZipArchive();
        if ($this->zip->open($filePath) !== true) {
            throw new RuntimeException("Cannot open XLSX file: $filePath");
        }
        $this->loadSharedStrings();
        $this->loadSheetList();
    }

    public function __destruct() {
        if ($this->zip) {
            $this->zip->close();
        }
    }

    // ─── Public API ─────────────────────────────────────────────────────────

    /** Returns sheet names (1-indexed keys) */
    public function getSheetNames(): array {
        return $this->sheets;
    }

    /**
     * Read all rows of a sheet.
     * @param int $sheetIndex 1-based
     * @return array  [ [col => value, ...], ... ]  (first row = headers if $hasHeaders)
     */
    public function getRows(int $sheetIndex = 1, bool $hasHeaders = true): array {
        $xml = $this->getSheetXml($sheetIndex);
        if (!$xml) return [];

        $rawRows = $this->parseRows($xml);
        if (empty($rawRows)) return [];

        if (!$hasHeaders) return $rawRows;

        $headers = array_shift($rawRows);
        $result  = [];
        foreach ($rawRows as $row) {
            $mapped = [];
            foreach ($headers as $col => $header) {
                $mapped[trim((string)$header)] = $row[$col] ?? '';
            }
            // Skip completely empty rows
            if (array_filter($mapped, fn($v) => $v !== '')) {
                $result[] = $mapped;
            }
        }
        return $result;
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    private function loadSharedStrings(): void {
        $xml = $this->readEntry('xl/sharedStrings.xml');
        if (!$xml) return;

        $dom = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        foreach ($dom->si as $si) {
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $this->sharedStrings[] = $text;
            } else {
                $this->sharedStrings[] = '';
            }
        }
    }

    private function loadSheetList(): void {
        $xml = $this->readEntry('xl/workbook.xml');
        if (!$xml) return;

        $dom = simplexml_load_string($xml);
        $dom->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($dom->sheets->sheet as $sheet) {
            $this->sheets[] = (string)$sheet['name'];
        }
    }

    private function getSheetXml(int $index): ?string {
        return $this->readEntry("xl/worksheets/sheet{$index}.xml");
    }

    private function readEntry(string $name): ?string {
        $content = $this->zip->getFromName($name);
        return $content !== false ? $content : null;
    }

    private function parseRows(string $xml): array {
        $dom = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $dom->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        $maxCol = 0;

        foreach ($dom->sheetData->row as $row) {
            $rowIdx  = (int)$row['r'] - 1;
            $rowData = [];

            foreach ($row->c as $cell) {
                $colRef  = (string)$cell['r'];
                $colLet  = preg_replace('/[0-9]/', '', $colRef);
                $colIdx  = $this->colToIndex($colLet);
                $maxCol  = max($maxCol, $colIdx);
                $type    = (string)$cell['t'];
                $rawVal  = (string)$cell->v;

                $rowData[$colIdx] = $this->resolveValue($rawVal, $type);
            }
            $rows[$rowIdx] = $rowData;
        }

        // Normalise — fill missing columns with ''
        $normalised = [];
        foreach ($rows as $r) {
            $norm = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $norm[$c] = $r[$c] ?? '';
            }
            $normalised[] = $norm;
        }
        return $normalised;
    }

    private function resolveValue(string $raw, string $type): string {
        if ($type === 's') {
            return $this->sharedStrings[(int)$raw] ?? '';
        }
        if ($type === 'b') {
            return $raw === '1' ? 'TRUE' : 'FALSE';
        }
        if ($type === 'inlineStr') {
            return $raw;
        }
        // Numeric date (Excel serial) — only convert if it looks like a date
        // Leave plain numbers as-is
        return $raw;
    }

    private function colToIndex(string $col): int {
        $col   = strtoupper($col);
        $index = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - 64);
        }
        return $index - 1;
    }
}
