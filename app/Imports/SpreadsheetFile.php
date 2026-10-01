<?php

namespace App\Imports;

use DateTimeInterface;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Reads import files (.xlsx first sheet, or .csv) and writes templates.
 */
final class SpreadsheetFile
{
    public const MAX_ROWS = 50000;

    /**
     * @return array{headers: list<string>, rows: array<int, array<string, mixed>>}  rows keyed by spreadsheet row number
     */
    public static function read(string $path, string $extension): array
    {
        $raw = strtolower($extension) === 'xlsx' ? self::readXlsx($path) : self::readCsv($path);

        $headerIndex = null;
        foreach ($raw as $number => $cells) {
            if (collect($cells)->filter(fn ($c) => trim((string) self::scalar($c)) !== '')->isNotEmpty()) {
                $headerIndex = $number;
                break;
            }
        }
        if ($headerIndex === null) {
            throw new RuntimeException('The file is empty.');
        }

        $headers = array_map(fn ($h) => self::headerKey((string) self::scalar($h)), $raw[$headerIndex]);
        $rows = [];

        foreach ($raw as $number => $cells) {
            if ($number <= $headerIndex) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $value = $cells[$i] ?? null;
                $row[$header] = $value instanceof DateTimeInterface ? $value : (($s = trim((string) self::scalar($value))) === '' ? null : $s);
            }
            if (collect($row)->filter(fn ($v) => $v !== null)->isEmpty()) {
                continue; // blank line
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('The file has more than '.number_format(self::MAX_ROWS).' rows. Split it, or use the command line: php artisan emr:import.');
            }
            $rows[$number] = $row;
        }

        return ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
    }

    /**
     * "First Name *" → first_name
     */
    public static function headerKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = preg_replace('/\(.*?\)|\*/', '', $header);

        return Str::of($header)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    /**
     * Template as .xlsx: a "Data" sheet with the headings (and sample rows),
     * plus an "Instructions" sheet describing every column.
     */
    public static function writeTemplate(Importer $importer, string $path, bool $withSamples): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $columns = $importer->columns();
        $head = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('2A78D6');
        $required = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('B02A37');
        $text = (new Style)->setFormat('@'); // keep phone numbers' leading zeros

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data');
        foreach (array_keys($columns) as $i) {
            $sheet->setColumnWidth(max(14, min(40, strlen($columns[$i]->name) + 6)), $i + 1);
        }
        $writer->addRow(Row::fromValuesWithStyles(
            array_map(fn (ImportColumn $c) => $c->name, $columns), null,
            array_map(fn (ImportColumn $c) => $c->required ? $required : $head, $columns),
        ));
        if ($withSamples) {
            foreach (self::sampleRows($importer) as $sample) {
                $writer->addRow(Row::fromValuesWithStyles($sample, null, array_fill(0, count($sample), $text)));
            }
        }

        $info = $writer->addNewSheetAndMakeItCurrent();
        $info->setName('Instructions');
        $info->setColumnWidth(24, 1);
        $info->setColumnWidth(11, 2);
        $info->setColumnWidth(60, 3);
        $info->setColumnWidth(24, 4);
        $bold = (new Style)->setFontBold();
        $wrap = (new Style)->setShouldWrapText();

        $writer->addRow(Row::fromValues([$importer->title().' — import template'], (new Style)->setFontBold()->setFontSize(14)));
        $writer->addRow(Row::fromValues([$importer->description()]));
        $writer->addRow(Row::fromValues(['Matching: '.$importer->matchDescription()]));
        foreach ($importer->notes() as $note) {
            $writer->addRow(Row::fromValues(['• '.$note]));
        }
        $writer->addRow(Row::fromValues(['• Fill the "Data" sheet: one record per row, headings unchanged. Red headings are required.']));
        $writer->addRow(Row::fromValues(['• Format phone-number and ID columns as Text so leading zeros are kept.']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Column', 'Required', 'What to enter', 'Example'], $bold));
        foreach ($columns as $c) {
            $what = trim(($c->help ?? $c->label).($c->allowed ? ' Allowed: '.implode(', ', $c->allowed).'.' : ''));
            $writer->addRow(Row::fromValues([$c->name, $c->required ? 'Yes' : '', $what, $c->example ?? ''], $wrap));
        }

        $writer->close();
    }

    public static function writeCsvTemplate(Importer $importer, bool $withSamples): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(fn (ImportColumn $c) => $c->name, $importer->columns()));
        if ($withSamples) {
            foreach (self::sampleRows($importer) as $sample) {
                fputcsv($out, $sample);
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * @return list<list<string>>
     */
    public static function sampleRows(Importer $importer): array
    {
        $columns = $importer->columns();
        $rows = [array_map(fn (ImportColumn $c) => (string) ($c->example ?? ''), $columns)];
        if (collect($columns)->contains(fn (ImportColumn $c) => $c->example2 !== null)) {
            $rows[] = array_map(fn (ImportColumn $c) => (string) ($c->example2 ?? ''), $columns);
        }

        return $rows;
    }

    /**
     * @return array<int, list<mixed>>  keyed by row number (1-based)
     */
    protected static function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $n = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[++$n] = $row->toArray();
                    if ($n > self::MAX_ROWS + 50) {
                        break;
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @return array<int, list<string>>
     */
    protected static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('The file could not be read.');
        }

        // Excel in many locales saves CSV with semicolons.
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        $n = 0;
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $cells = array_map(fn ($c) => self::toUtf8((string) $c), $cells);
            $rows[++$n] = $cells;
            if ($n > self::MAX_ROWS + 50) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    protected static function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * Cell value → scalar; whole-number floats lose their ".0" (08031234567 stored as number → 8031234567).
     */
    protected static function scalar(mixed $value): mixed
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value instanceof \DateInterval) {
            return $value->format('%H:%I');
        }

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
