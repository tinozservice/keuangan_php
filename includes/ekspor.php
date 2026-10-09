<?php
/** Ekspor data workspace (FR-034/035) — CSV UTF-8 & Excel .xlsx tanpa dependensi eksternal. */

/** Hasil unduhan CSV transaksi (BOM UTF-8, pemisah titik-koma agar rapi di Excel Indonesia). */
function export_tx_csv(array $rows): string
{
    $lines = [];
    $lines[] = export_csv_line(['Tanggal', 'Jenis', 'Deskripsi', 'Rekening', 'Nominal']);
    foreach ($rows as $row) {
        $lines[] = export_csv_line([
            (string) $row['tx_date'],
            ((string) $row['type']) === 'masuk' ? 'Masuk' : 'Keluar',
            (string) $row['description'],
            (string) ($row['account_name'] ?? ''),
            (string) (int) $row['amount'],
        ]);
    }
    return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
}

function export_csv_line(array $fields): string
{
    return implode(';', array_map('export_csv_field', $fields));
}

function export_csv_field(string $value): string
{
    $value = export_safe_cell($value);
    if (preg_match('/[;"\r\n]/', $value) === 1) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

/** Cegah eksekusi formula saat berkas CSV dibuka di Excel (CSV injection). */
function export_safe_cell(string $value): string
{
    if ($value !== '' && str_contains('=+-@', $value[0])) {
        return "'" . $value;
    }
    return $value;
}

/** Berkas Excel (.xlsx) berisi lembar "Transaksi" — ZIP ditulis manual (ekstensi zip tidak wajib). */
function export_tx_xlsx(array $rows): string
{
    $sheetRows = [];
    $sheetRows[] = export_xlsx_row(1, [
        ['Tanggal', 's'], ['Jenis', 's'], ['Deskripsi', 's'], ['Rekening', 's'], ['Nominal', 's'],
    ], true);

    $rowNumber = 2;
    foreach ($rows as $row) {
        $sheetRows[] = export_xlsx_row($rowNumber++, [
            [(string) $row['tx_date'], 's'],
            [((string) $row['type']) === 'masuk' ? 'Masuk' : 'Keluar', 's'],
            [(string) $row['description'], 's'],
            [(string) ($row['account_name'] ?? ''), 's'],
            [(string) (int) $row['amount'], 'n'],
        ], false);
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<cols>'
        . '<col min="1" max="1" width="12" customWidth="1"/>'
        . '<col min="2" max="2" width="9" customWidth="1"/>'
        . '<col min="3" max="3" width="42" customWidth="1"/>'
        . '<col min="4" max="4" width="18" customWidth="1"/>'
        . '<col min="5" max="5" width="14" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '</worksheet>';

    return export_zip_store([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Transaksi" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

/** Satu baris sheet XML; $cells = daftar [nilai, tipe('s'|'n')]. */
function export_xlsx_row(int $rowNumber, array $cells, bool $bold): string
{
    $xml = '';
    $col = 1;
    foreach ($cells as $cell) {
        [$value, $kind] = $cell;
        $ref = export_cell_ref($col++, $rowNumber);
        $style = $bold ? ' s="1"' : '';
        if ($kind === 'n') {
            $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
        } else {
            $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . export_xml($value) . '</t></is></c>';
        }
    }
    return '<row r="' . $rowNumber . '">' . $xml . '</row>';
}

function export_cell_ref(int $col, int $row): string
{
    $letters = '';
    while ($col > 0) {
        $col--;
        $letters = chr(65 + ($col % 26)) . $letters;
        $col = intdiv($col, 26);
    }
    return $letters . (string) $row;
}

function export_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Slug nama berkas (ASCII, huruf kecil). */
function export_slug(string $value): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    if ($ascii === false || $ascii === '') {
        $ascii = $value;
    }
    $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $ascii), '-');
    $slug = strtolower($slug);
    return $slug === '' ? 'workspace' : substr($slug, 0, 40);
}

/** Bangun ZIP dari daftar nama => isi (metode store) tanpa ekstensi zip. */
function export_zip_store(array $files): string
{
    $now = getdate();
    $dosTime = (($now['hours'] << 11) | ($now['minutes'] << 5) | ($now['seconds'] >> 1)) & 0xFFFF;
    $dosDate = ((($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday']) & 0xFFFF;

    $body = '';
    $central = '';
    $offset = 0;

    foreach ($files as $name => $data) {
        $crc = crc32($data);
        $size = strlen($data);
        $nameLength = strlen($name);

        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0) . $name;
        $body .= $local . $data;

        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;

        $offset += strlen($local) + $size;
    }

    $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);

    return $body . $central . $eocd;
}
