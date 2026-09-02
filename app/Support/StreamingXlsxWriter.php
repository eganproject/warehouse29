<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipStream\ZipStream;

class StreamingXlsxWriter
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private Filesystem $files)
    {
    }

    public function download(string $filename, array $headings, iterable $rows, array $columnWidths = []): BinaryFileResponse
    {
        return $this->downloadWorkbook($filename, [[
            'name' => 'Stock Mutations',
            'headings' => $headings,
            'rows' => $rows,
            'column_widths' => $columnWidths,
        ]]);
    }

    public function downloadWorkbook(string $filename, array $sheets): BinaryFileResponse
    {
        $path = $this->writeWorkbook($sheets);

        try {
            return response()->download($path, $filename, [
                'Content-Type' => self::CONTENT_TYPE,
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            $this->files->delete($path);

            throw $exception;
        }
    }

    public function write(array $headings, iterable $rows, array $columnWidths = []): string
    {
        return $this->writeWorkbook([[
            'name' => 'Stock Mutations',
            'headings' => $headings,
            'rows' => $rows,
            'column_widths' => $columnWidths,
        ]]);
    }

    public function writeWorkbook(array $sheets): string
    {
        $directory = $this->temporaryDirectory();

        $sheets = $this->normalizeSheets($sheets);
        $sheetPaths = [];
        $xlsxPath = tempnam($directory, 'stock-mutations-');

        if ($xlsxPath === false) {
            if (is_string($xlsxPath)) {
                $this->files->delete($xlsxPath);
            }

            throw new RuntimeException('Gagal membuat file sementara untuk export Stock Mutation.');
        }

        try {
            foreach ($sheets as $index => $sheet) {
                $sheetPath = tempnam($directory, 'stock-mutations-sheet-');
                if ($sheetPath === false) {
                    throw new RuntimeException('Gagal membuat worksheet sementara untuk export Stock Mutation.');
                }

                $sheetPaths[] = $sheetPath;
                $this->writeWorksheet(
                    $sheetPath,
                    $sheet['headings'],
                    $sheet['rows'],
                    $sheet['column_widths']
                );
                $sheets[$index]['path'] = $sheetPath;
            }

            $this->writeArchive($xlsxPath, $sheets);

            return $xlsxPath;
        } catch (\Throwable $exception) {
            $this->files->delete($xlsxPath);

            throw $exception;
        } finally {
            $this->files->delete($sheetPaths);
        }
    }

    private function temporaryDirectory(): string
    {
        $candidates = [
            storage_path('framework/cache/laravel-excel'),
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'warehouse29-excel',
        ];

        foreach ($candidates as $directory) {
            try {
                $this->files->ensureDirectoryExists($directory);
                if (is_writable($directory)) {
                    return $directory;
                }
            } catch (\Throwable) {
                // Try the next writable temporary directory.
            }
        }

        throw new RuntimeException('Direktori temporary export tidak dapat ditulis oleh server.');
    }

    private function normalizeSheets(array $sheets): array
    {
        if ($sheets === []) {
            throw new RuntimeException('Workbook export harus memiliki minimal satu worksheet.');
        }

        $normalized = [];
        $usedNames = [];

        foreach ($sheets as $index => $sheet) {
            if (! is_array($sheet) || ! isset($sheet['headings'], $sheet['rows']) || ! is_iterable($sheet['rows'])) {
                throw new RuntimeException('Konfigurasi worksheet export tidak valid.');
            }

            $baseName = preg_replace('/[\\\\\/\?\*\[\]\:]/', ' ', (string) ($sheet['name'] ?? 'Sheet '.($index + 1)));
            $baseName = trim(mb_substr((string) $baseName, 0, 31, 'UTF-8')) ?: 'Sheet '.($index + 1);
            $name = $baseName;
            $suffix = 2;

            while (isset($usedNames[mb_strtolower($name, 'UTF-8')])) {
                $ending = ' ('.$suffix.')';
                $name = mb_substr($baseName, 0, 31 - mb_strlen($ending, 'UTF-8'), 'UTF-8').$ending;
                $suffix++;
            }

            $usedNames[mb_strtolower($name, 'UTF-8')] = true;
            $normalized[] = [
                'name' => $name,
                'headings' => array_values((array) $sheet['headings']),
                'rows' => $sheet['rows'],
                'column_widths' => (array) ($sheet['column_widths'] ?? []),
            ];
        }

        return $normalized;
    }

    private function writeWorksheet(string $path, array $headings, iterable $rows, array $columnWidths): void
    {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('Gagal membuka file sementara export Stock Mutation.');
        }

        try {
            $this->append($stream, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            $this->append($stream, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
            $this->append($stream, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
            $this->append($stream, '<sheetFormatPr defaultRowHeight="15"/>');
            $this->appendColumnWidths($stream, $columnWidths);
            $this->append($stream, '<sheetData>');
            $this->appendRow($stream, 1, $headings, true);

            $rowNumber = 2;
            foreach ($rows as $row) {
                if ($rowNumber > 1048576) {
                    throw new RuntimeException('Export XLSX maksimal memuat 1.048.575 baris data. Persempit filter tanggal.');
                }

                $this->appendRow($stream, $rowNumber, is_array($row) ? $row : (array) $row);
                $rowNumber++;
            }

            $lastColumn = $this->columnName(max(1, count($headings)));
            $lastRow = max(1, $rowNumber - 1);
            $this->append($stream, '</sheetData>');
            $this->append($stream, '<autoFilter ref="A1:'.$lastColumn.$lastRow.'"/>');
            $this->append($stream, '</worksheet>');
        } finally {
            fclose($stream);
        }
    }

    private function appendColumnWidths($stream, array $columnWidths): void
    {
        if ($columnWidths === []) {
            return;
        }

        $this->append($stream, '<cols>');
        foreach ($columnWidths as $column => $width) {
            $index = $this->columnIndex((string) $column);
            $numericWidth = max(1, (float) $width);
            $this->append($stream, '<col min="'.$index.'" max="'.$index.'" width="'.$numericWidth.'" customWidth="1"/>');
        }
        $this->append($stream, '</cols>');
    }

    private function appendRow($stream, int $rowNumber, array $values, bool $heading = false): void
    {
        $this->append($stream, '<row r="'.$rowNumber.'">');

        foreach (array_values($values) as $offset => $value) {
            if ($value === null) {
                continue;
            }

            $coordinate = $this->columnName($offset + 1).$rowNumber;
            $style = $heading ? ' s="1"' : '';

            if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
                $this->append($stream, '<c r="'.$coordinate.'"'.$style.'><v>'.$value.'</v></c>');
                continue;
            }

            if (is_bool($value)) {
                $this->append($stream, '<c r="'.$coordinate.'"'.$style.' t="b"><v>'.($value ? '1' : '0').'</v></c>');
                continue;
            }

            $text = StringHelper::controlCharacterPHP2OOXML(
                StringHelper::sanitizeUTF8((string) $value)
            );
            $text = mb_substr($text, 0, 32767, 'UTF-8');
            $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $this->append($stream, '<c r="'.$coordinate.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>');
        }

        $this->append($stream, '</row>');
    }

    private function writeArchive(string $xlsxPath, array $sheets): void
    {
        $output = fopen($xlsxPath, 'wb');
        if ($output === false) {
            throw new RuntimeException('Gagal membuat arsip XLSX Stock Mutation.');
        }

        try {
            $archive = new ZipStream(
                outputStream: $output,
                sendHttpHeaders: false,
                enableZip64: true
            );
            $archive->addFile('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
            $archive->addFile('_rels/.rels', $this->rootRelationshipsXml());
            $archive->addFile('xl/workbook.xml', $this->workbookXml($sheets));
            $archive->addFile('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml(count($sheets)));
            $archive->addFile('xl/styles.xml', $this->stylesXml());

            foreach ($sheets as $index => $sheet) {
                $archive->addFileFromPath(
                    'xl/worksheets/sheet'.($index + 1).'.xml',
                    $sheet['path']
                );
            }

            $archive->finish();
        } finally {
            fclose($output);
        }
    }

    private function append($stream, string $contents): void
    {
        if (fwrite($stream, $contents) === false) {
            throw new RuntimeException('Gagal menulis data export Stock Mutation.');
        }
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function columnIndex(string $name): int
    {
        $index = 0;
        foreach (str_split(strtoupper($name)) as $character) {
            $index = ($index * 26) + (ord($character) - 64);
        }

        return max(1, $index);
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $worksheets = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$worksheets
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(array $sheets): string
    {
        $sheetElements = '';
        foreach ($sheets as $index => $sheet) {
            $name = htmlspecialchars($sheet['name'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $sheetElements .= '<sheet name="'.$name.'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetElements.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
            .'<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }
}
