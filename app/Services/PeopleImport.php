<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\VotingException;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legt Personen an und ordnet sie Mitgliedern zu (Erstbefüllung).
 *
 * Erwartet eine Kopfzeile mit den Spalten „Mitglied“ (oder „Verein“),
 * „E-Mail“ und „Name“. Die Mitglieder müssen bereits existieren. Eine Person
 * kann in mehreren Zeilen stehen und vertritt dann mehrere Mitglieder.
 * Vorhandene Personen (gleiche E-Mail) werden nur zugeordnet, nicht geändert.
 */
class PeopleImport
{
    public const COLUMNS = [
        'club' => ['mitglied', 'verein', 'mitgliedsname', 'vereinsname'],
        'email' => ['e-mail', 'email', 'mail', 'e-mail-adresse'],
        'name' => ['name', 'person'],
    ];

    /**
     * @param  list<list<string>>  $rows  inklusive Kopfzeile
     * @return array{
     *     entries: list<array{line: int, club_id: int, club: string, email: string, name: string, action: 'create'|'attach'|'exists'}>,
     *     errors: list<array{line: int, message: string}>
     * }
     */
    public function plan(array $rows): array
    {
        $columns = $this->columns($rows[0] ?? []);
        $clubs = Club::query()->get(['id', 'name'])->keyBy(fn (Club $c) => Club::normalizeName($c->name));
        $users = User::query()->with('clubs:id')->get()->keyBy(fn (User $u) => Str::lower($u->email));

        $entries = [];
        $errors = [];
        /** @var array<string, list<int>> $planned E-Mail => Mitglieds-IDs aus vorherigen Zeilen */
        $planned = [];

        foreach (array_slice($rows, 1) as $i => $row) {
            $line = $i + 2;
            $clubName = trim($row[$columns['club']] ?? '');
            $email = Str::lower(trim($row[$columns['email']] ?? ''));
            $name = trim(preg_replace('/\s+/u', ' ', $row[$columns['name']] ?? '') ?? '');

            $club = $clubs->get(Club::normalizeName($clubName));
            $user = $users->get($email);

            $error = match (true) {
                $clubName === '' => 'Mitglied fehlt',
                $club === null => "Mitglied „{$clubName}“ gibt es nicht".$this->hint($clubName, $clubs->pluck('name')->all()),
                filter_var($email, FILTER_VALIDATE_EMAIL) === false => "Ungültige E-Mail-Adresse „{$email}“",
                $name === '' && $user === null && ! isset($planned[$email]) => 'Name fehlt',
                default => null,
            };

            if ($error !== null) {
                $errors[] = ['line' => $line, 'message' => $error];

                continue;
            }

            /** @var Club $club */
            $alreadyAttached = in_array($club->id, $planned[$email] ?? [], true)
                || ($user !== null && $user->clubs->contains('id', $club->id));

            $entries[] = [
                'line' => $line,
                'club_id' => $club->id,
                'club' => $club->name,
                'email' => $email,
                'name' => $user->name ?? ($name !== '' ? $name : $this->plannedName($entries, $email)),
                'action' => match (true) {
                    $alreadyAttached => 'exists',
                    $user === null && ! isset($planned[$email]) => 'create',
                    default => 'attach',
                },
            ];

            $planned[$email][] = $club->id;
        }

        return ['entries' => $entries, 'errors' => $errors];
    }

    /**
     * @param  list<array{line: int, club_id: int, club: string, email: string, name: string, action: string}>  $entries
     * @return array{created: list<User>, attached: int, unchanged: int}
     */
    public function apply(array $entries): array
    {
        return DB::transaction(function () use ($entries) {
            $created = [];
            $attached = 0;
            $unchanged = 0;

            foreach ($entries as $entry) {
                if ($entry['action'] === 'exists') {
                    $unchanged++;

                    continue;
                }

                $user = User::query()->whereRaw('lower(email) = ?', [$entry['email']])->first();

                if ($user === null) {
                    $user = User::create([
                        'name' => $entry['name'],
                        'email' => $entry['email'],
                        'password' => Str::password(32),
                        'role' => Role::Member,
                    ]);
                    $created[] = $user;
                }

                $changes = $user->clubs()->syncWithoutDetaching([$entry['club_id']]);
                $attached += count($changes['attached']);
            }

            return ['created' => $created, 'attached' => $attached, 'unchanged' => $unchanged];
        });
    }

    /**
     * @param  list<string>  $header
     * @return array{club: int, email: int, name: int}
     */
    private function columns(array $header): array
    {
        $normalized = array_map(fn ($cell) => Str::lower(trim($cell)), $header);
        $columns = [];
        $missing = [];

        foreach (self::COLUMNS as $key => $aliases) {
            $index = collect($normalized)->search(fn ($cell) => in_array($cell, $aliases, true));
            if ($index === false) {
                $missing[] = $aliases[0];
            } else {
                $columns[$key] = (int) $index;
            }
        }

        if ($missing !== []) {
            throw new VotingException(
                'Spalte(n) fehlen: '.implode(', ', array_map('ucfirst', $missing)).'. '
                .'Erwartet wird eine Kopfzeile mit „Mitglied“, „E-Mail“ und „Name“.'
            );
        }

        /** @var array{club: int, email: int, name: int} $columns */
        return $columns;
    }

    /**
     * @param  list<array{email: string, name: string}>  $entries
     */
    private function plannedName(array $entries, string $email): string
    {
        foreach ($entries as $entry) {
            if ($entry['email'] === $email) {
                return $entry['name'];
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $names
     */
    private function hint(string $name, array $names): string
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($names as $candidate) {
            similar_text(Club::normalizeName($name), Club::normalizeName($candidate), $percent);
            if ($percent > $bestScore) {
                [$best, $bestScore] = [$candidate, $percent];
            }
        }

        return $bestScore >= 70 ? " (meinst du „{$best}“?)" : '';
    }
}
