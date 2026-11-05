<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Liest das erste Tabellenblatt einer CSV- oder XLSX-Datei als Liste von
 * Zeilen (alle Zellen als getrimmte Strings, leere Zeilen entfernt).
 *
 * CSV: Trenner (Semikolon, Komma, Tab) wird erkannt, BOM entfernt,
 * Windows-1252 nach UTF-8 umgewandelt.
 */
class SpreadsheetReader
{
    /**
     * Liest die erste Tabelle einer CSV- oder XLSX-Datei als Liste von Zeilen.
     *
     * @return list<list<string>>
     */
    public function read(string $path, string $extension): array
    {
        $rows = Str::lower($extension) === 'xlsx'
            ? $this->readXlsx($path)
            : $this->readCsv($path);

        // Leere Zeilen und überzählige leere Zellen entfernen
        return array_values(array_filter(
            array_map(fn (array $row) => array_map(fn ($cell) => trim((string) $cell), $row), $rows),
            fn (array $row) => implode('', $row) !== '',
        ));
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        // Excel speichert CSV unter Windows oft als Windows-1252
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([';', ',', "\t"])
            ->sortByDesc(fn ($d) => substr_count($firstLine, $d))
            ->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($cell) => (string) $cell, $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(function ($value) {
                    if (is_float($value) && floor($value) === $value) {
                        return (string) (int) $value;
                    }

                    return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
                }, $row->toArray());
            }
            break; // nur das erste Tabellenblatt
        }

        $reader->close();

        return $rows;
    }
}
