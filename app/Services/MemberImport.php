<?php

namespace App\Services;

use App\Exceptions\VotingException;
use App\Models\Club;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Import von Mitgliedern (Name + Mitgliederzahl) aus CSV oder XLSX.
 *
 * Ablauf: read() → columns/guessColumns() → rows() schlägt je Zeile ein Ziel
 * vor (vorhandenes Mitglied mit gleichem Namen, sonst „neu“) → die Zuordnung
 * kann geändert werden (z.B. bei Umbenennung) → apply() übernimmt alles in
 * einer Transaktion.
 */
class MemberImport
{
    public const NEW = 'new';

    public const SKIP = 'skip';

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
     * Rät die Spalten für Name und Mitgliederzahl sowie, ob es eine Kopfzeile gibt.
     *
     * @param  list<list<string>>  $rows
     * @return array{name: int, count: int, header: bool}
     */
    public function guessColumns(array $rows): array
    {
        $first = $rows[0] ?? [];
        $name = null;
        $count = null;

        foreach ($first as $i => $cell) {
            $label = Str::lower($cell);
            if ($count === null && Str::contains($label, ['anzahl', 'zahl', 'count', 'größe', 'mitglieder'])) {
                $count = $i;
            } elseif ($name === null && Str::contains($label, ['name', 'verein', 'mitglied', 'bezeichnung'])) {
                $name = $i;
            }
        }

        $header = $name !== null || $count !== null;

        if (! $header) {
            // Ohne Kopfzeile: erste Textspalte = Name, erste Zahlenspalte = Anzahl
            foreach ($first as $i => $cell) {
                if ($count === null && $this->parseCount($cell) !== null) {
                    $count = $i;
                } elseif ($name === null && $this->parseCount($cell) === null) {
                    $name = $i;
                }
            }
        }

        $name ??= 0;
        $count ??= $name === 0 ? 1 : 0;

        return ['name' => $name, 'count' => $count, 'header' => $header];
    }

    /**
     * Baut die Importzeilen mit Vorschlag für die Zuordnung.
     *
     * @param  list<list<string>>  $rows
     * @return list<array{line: int, name: string, count: int|null, error: string|null, target: string, rename: bool, similar: string|null}>
     */
    public function rows(array $rows, int $nameColumn, int $countColumn, bool $hasHeader): array
    {
        $clubs = Club::query()->orderBy('name')->get(['id', 'name']);
        $byName = $clubs->keyBy(fn (Club $c) => $this->normalize($c->name));
        $result = [];

        foreach ($rows as $index => $row) {
            if ($hasHeader && $index === 0) {
                continue;
            }

            $name = preg_replace('/\s+/u', ' ', $row[$nameColumn] ?? '') ?? '';
            $rawCount = $row[$countColumn] ?? '';
            $count = $this->parseCount($rawCount);

            $error = match (true) {
                $name === '' => 'Kein Name',
                $count === null => "Ungültige Mitgliederzahl „{$rawCount}“",
                default => null,
            };

            $match = $byName->get($this->normalize($name));

            $result[] = [
                'line' => $index + 1,
                'name' => $name,
                'count' => $count,
                'error' => $error,
                'target' => $error !== null ? self::SKIP : ($match ? (string) $match->id : self::NEW),
                // Bei abweichender Schreibweise eines Treffers den Namen übernehmen
                'rename' => true,
                'similar' => ($match === null && $error === null) ? $this->similar($name, $clubs) : null,
            ];
        }

        return $result;
    }

    /**
     * Übernimmt die Zuordnung. Wirft eine VotingException mit allen Problemen,
     * wenn die Zuordnung nicht eindeutig ist – dann wird nichts geändert.
     *
     * @param  list<array{line: int, name: string, count: int|null, error: string|null, target: string, rename: bool}>  $rows
     * @return array{created: int, updated: int, renamed: int, unchanged: int, skipped: int}
     */
    public function apply(array $rows): array
    {
        $this->validate($rows);

        return DB::transaction(function () use ($rows) {
            $summary = ['created' => 0, 'updated' => 0, 'renamed' => 0, 'unchanged' => 0, 'skipped' => 0];

            $updates = [];
            foreach ($rows as $row) {
                if ($row['target'] === self::SKIP || $row['error'] !== null) {
                    $summary['skipped']++;
                } elseif ($row['target'] !== self::NEW) {
                    $updates[] = $row;
                }
            }

            // Umbenennungen in zwei Phasen, damit Namenstausche nicht zwischendurch
            // am Unique-Index scheitern (A → B, während B noch so heißt).
            $clubs = Club::query()->findMany(array_map(fn ($r) => (int) $r['target'], $updates))->keyBy('id');
            $renames = [];
            foreach ($updates as $row) {
                $club = $clubs[(int) $row['target']];
                if ($row['rename'] && $club->name !== $row['name']) {
                    $renames[$club->id] = $row['name'];
                    $club->forceFill(['name' => "__import_{$club->id}_".Str::random(8)])->saveQuietly();
                }
            }

            foreach ($updates as $row) {
                $club = $clubs[(int) $row['target']];
                $club->member_count = (int) $row['count'];
                if (isset($renames[$club->id])) {
                    $club->name = $renames[$club->id];
                }

                if (! isset($renames[$club->id]) && ! $club->isDirty()) {
                    $summary['unchanged']++;

                    continue;
                }

                $club->save();
                $summary['updated']++;
                $summary['renamed'] += isset($renames[$club->id]) ? 1 : 0;
            }

            // Neue Mitglieder zuletzt: so kann ein neuer Eintrag den alten Namen
            // eines umbenannten Mitglieds bekommen.

            foreach ($rows as $row) {
                if ($row['target'] === self::NEW && $row['error'] === null) {
                    Club::create(['name' => $row['name'], 'member_count' => $row['count']]);
                    $summary['created']++;
                }
            }

            return $summary;
        });
    }

    /**
     * @param  list<array{line: int, name: string, count: int|null, error: string|null, target: string, rename: bool}>  $rows
     */
    private function validate(array $rows): void
    {
        $problems = [];
        $active = array_filter($rows, fn ($r) => $r['target'] !== self::SKIP && $r['error'] === null);
        $existing = Club::query()->pluck('name', 'id');

        foreach ($active as $row) {
            if (trim($row['name']) === '' || ! is_int($row['count']) || $row['count'] < 0) {
                $problems[] = "Zeile {$row['line']}: Name oder Mitgliederzahl fehlt.";
            }
            if ($row['target'] !== self::NEW && ! $existing->has((int) $row['target'])) {
                $problems[] = "Zeile {$row['line']}: Das zugeordnete Mitglied gibt es nicht mehr.";
            }
        }
        if ($problems !== []) {
            throw new VotingException(implode("\n", $problems));
        }

        // Jedes vorhandene Mitglied höchstens einmal
        $targets = collect($active)->where('target', '!=', self::NEW)->groupBy('target');
        foreach ($targets->filter(fn ($group) => $group->count() > 1) as $id => $group) {
            $problems[] = '„'.$existing[(int) $id].'“ ist mehreren Zeilen zugeordnet (Zeilen '.$group->pluck('line')->implode(', ').').';
        }

        // Endgültige Namen müssen eindeutig sein
        $final = $existing->all();
        $newNames = [];
        foreach ($active as $row) {
            if ($row['target'] === self::NEW) {
                $newNames[] = $row;
            } elseif ($row['rename']) {
                $final[(int) $row['target']] = $row['name'];
            }
        }

        $taken = collect($final)->countBy(fn ($name) => $this->normalize($name));
        foreach ($newNames as $row) {
            $key = $this->normalize($row['name']);
            if (($taken[$key] ?? 0) > 0) {
                $problems[] = "Zeile {$row['line']}: „{$row['name']}“ gibt es schon. Bitte dem vorhandenen Mitglied zuordnen oder überspringen.";
            }
            $taken[$key] = ($taken[$key] ?? 0) + 1;
        }
        foreach ($taken->filter(fn ($n) => $n > 1) as $key => $n) {
            $names = collect($final)->filter(fn ($name) => $this->normalize($name) === $key);
            if ($names->count() > 1) {
                $problems[] = 'Nach dem Umbenennen gäbe es „'.$names->first().'“ mehrfach.';
            }
        }

        if ($problems !== []) {
            throw new VotingException(implode("\n", array_unique($problems)));
        }
    }

    public function parseCount(string $value): ?int
    {
        // "1.234" oder "1 234" als Tausendertrennung zulassen, sonst nur ganze Zahlen
        $clean = preg_replace('/[\s.\x{00A0}]/u', '', $value) ?? '';

        if (preg_match('/^\d+$/', $clean) !== 1) {
            return null;
        }

        return (int) $clean;
    }

    private function normalize(string $name): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($name)) ?? '');
    }

    /**
     * Ähnlichster vorhandener Name als Hinweis bei möglichen Umbenennungen.
     *
     * @param  Collection<int, Club>  $clubs
     */
    private function similar(string $name, Collection $clubs): ?string
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($clubs as $club) {
            similar_text($this->normalize($name), $this->normalize($club->name), $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $club->name;
            }
        }

        return $bestScore >= 70 ? $best : null;
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

                    return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
                }, $row->toArray());
            }
            break; // nur das erste Tabellenblatt
        }

        $reader->close();

        return $rows;
    }
}
