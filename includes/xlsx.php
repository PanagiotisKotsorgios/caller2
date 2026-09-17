<?php

declare(strict_types=1);

/**
 * Tiny XLSX reader for simple lead-import spreadsheets.
 * Framework-free / Composer-free. Supports shared strings, inline strings and numbers.
 */
function xlsx_reader_from_string(string $xml): XMLReader
{
    $reader = new XMLReader();
    if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
        throw new RuntimeException('Invalid XML inside XLSX file.');
    }
    return $reader;
}

function xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) return [];

    $reader = xlsx_reader_from_string($xml);
    $strings = [];
    $current = '';
    $insideSi = false;
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            $insideSi = true;
            $current = '';
            continue;
        }
        if ($insideSi && $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
            if ($reader->read() && in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE], true)) {
                $current .= $reader->value;
            }
            continue;
        }
        if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
            $strings[] = $current;
            $insideSi = false;
        }
    }
    $reader->close();
    return $strings;
}

function xlsx_sheet_paths(ZipArchive $zip): array
{
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) return [];

    $relMap = [];
    $reader = xlsx_reader_from_string($relsXml);
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Relationship') continue;
        $id = (string)$reader->getAttribute('Id');
        $target = (string)$reader->getAttribute('Target');
        if ($target === '') continue;
        if (str_starts_with($target, '/')) $target = ltrim($target, '/');
        else $target = 'xl/' . ltrim($target, '/');
        $relMap[$id] = $target;
    }
    $reader->close();

    $paths = [];
    $reader = xlsx_reader_from_string($workbookXml);
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sheet') continue;
        $name = (string)$reader->getAttribute('name');
        $rid = (string)($reader->getAttribute('r:id') ?: $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'));
        if ($rid !== '' && isset($relMap[$rid])) $paths[] = ['name' => $name, 'path' => $relMap[$rid]];
    }
    $reader->close();
    return $paths;
}

function xlsx_col_index(string $cellRef): int
{
    if (!preg_match('/^([A-Z]+)/i', $cellRef, $m)) return 0;
    $letters = strtoupper($m[1]);
    $index = 0;
    for ($i = 0, $n = strlen($letters); $i < $n; $i++) $index = $index * 26 + (ord($letters[$i]) - 64);
    return $index - 1;
}

function xlsx_read_sheet_rows(string $xml, array $shared): array
{
    $reader = xlsx_reader_from_string($xml);
    $rows = [];
    $currentRow = null;

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
            $currentRow = [];
            continue;
        }

        if ($currentRow !== null && $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c') {
            $ref = (string)$reader->getAttribute('r');
            $type = (string)$reader->getAttribute('t');
            $col = xlsx_col_index($ref);
            $cellDepth = $reader->depth;
            $raw = '';

            if (!$reader->isEmptyElement) {
                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->localName, ['v', 't'], true)) {
                        $valueTag = $reader->localName;
                        if ($reader->read() && in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE], true)) {
                            // For rich inline strings there may be multiple <t> nodes; concatenate them.
                            if ($valueTag === 't') $raw .= $reader->value;
                            else $raw = $reader->value;
                        }
                        continue;
                    }
                    if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $cellDepth) break;
                }
            }

            if ($type === 's') $value = $shared[(int)$raw] ?? '';
            elseif ($type === 'b') $value = $raw === '1' ? '1' : '0';
            else $value = $raw;
            $currentRow[$col] = trim($value);
            continue;
        }

        if ($currentRow !== null && $reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row') {
            if ($currentRow) {
                $max = max(array_keys($currentRow));
                $normalized = [];
                for ($i = 0; $i <= $max; $i++) $normalized[] = $currentRow[$i] ?? '';
                $rows[] = $normalized;
            }
            $currentRow = null;
        }
    }
    $reader->close();
    return $rows;
}

function xlsx_read_rows(string $file): array
{
    if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
        throw new RuntimeException('XLSX support is not installed. Redeploy using the included Dockerfile.');
    }

    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('Could not open XLSX file.');

    try {
        $shared = xlsx_shared_strings($zip);
        $sheets = xlsx_sheet_paths($zip);
        if (!$sheets) throw new RuntimeException('No worksheets were found in the XLSX file.');

        foreach ($sheets as $sheetInfo) {
            $xml = $zip->getFromName($sheetInfo['path']);
            if ($xml === false) continue;
            $rows = xlsx_read_sheet_rows($xml, $shared);
            foreach ($rows as $row) {
                if (in_array('Όνομα Επιχείρησης', $row, true) || in_array('Ονομα Επιχείρησης', $row, true)) {
                    return ['sheet' => $sheetInfo['name'], 'rows' => $rows];
                }
            }
        }
    } finally {
        $zip->close();
    }

    throw new RuntimeException('Could not find a sheet with an "Όνομα Επιχείρησης" column.');
}

function normalize_import_header(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace(['ά','έ','ή','ί','ό','ύ','ώ','ϊ','ΐ','ϋ','ΰ'], ['α','ε','η','ι','ο','υ','ω','ι','ι','υ','υ'], $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $value;
}

function import_header_map(array $headers): array
{
    $aliases = [
        'company_name' => ['ονομα επιχειρησης','εταιρεια','επιχειρηση','company','business'],
        'address' => ['διευθυνση','address'],
        'city' => ['περιοχη / πολη','περιοχη/πολη','πολη','περιοχη','city'],
        'region' => ['νομος / π.ε.','νομος / πε','νομος/π.ε.','νομος','π.ε.','region'],
        'phone' => ['τηλεφωνο','τηλ','phone'],
        'category' => ['κατηγορια κλαδου','κατηγορια','category'],
        'subcategory' => ['υποκατηγορια','subcategory'],
        'source_comments' => ['πηγη / σχολια','πηγη/σχολια','πηγη','σχολια','source'],
        'status' => ['κατασταση','status'],
        'email' => ['email','e-mail'],
        'contact_name' => ['υπευθυνος','ονομα επαφης','contact'],
    ];

    $map = [];
    foreach ($headers as $i => $header) {
        $h = normalize_import_header((string)$header);
        foreach ($aliases as $field => $names) {
            if (in_array($h, $names, true)) { $map[$field] = (int)$i; break; }
        }
    }
    return $map;
}

function import_status_value(string $value): string
{
    $v = normalize_import_header($value);
    $map = [
        'νεο' => 'new', 'new' => 'new',
        'δεν απαντησε' => 'no_answer', 'no answer' => 'no_answer',
        'επικοινωνια' => 'contacted', 'contacted' => 'contacted',
        'σταλθηκε demo' => 'demo_sent', 'demo sent' => 'demo_sent',
        'follow up' => 'follow_up', 'επανεπικοινωνια' => 'follow_up',
        'ενδιαφερεται' => 'interested', 'interested' => 'interested',
        'πωληση' => 'won', 'κερδισμενο' => 'won', 'won' => 'won', 'sold' => 'won',
        'χαμενο' => 'lost', 'lost' => 'lost',
        'δεν ενδιαφερεται' => 'not_interested', 'not interested' => 'not_interested',
    ];
    return $map[$v] ?? 'new';
}
