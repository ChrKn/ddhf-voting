<?php

namespace App\Console\Commands;

use App\Exceptions\VotingException;
use App\Models\User;
use App\Services\PeopleImport;
use App\Services\SpreadsheetReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Throwable;

#[Signature('voting:import-people
    {file : CSV- oder XLSX-Datei mit den Spalten Mitglied, E-Mail, Name}
    {--dry-run : Nur anzeigen, was passieren würde}
    {--invite : Neu angelegten Personen eine Einladung (Passwort setzen) schicken}
    {--skip-invalid : Fehlerhafte Zeilen überspringen statt abzubrechen}
    {--force : Ohne Rückfrage ausführen}')]
#[Description('Legt Personen an und ordnet sie vorhandenen Mitgliedern zu (Erstbefüllung)')]
class ImportPeople extends Command
{
    private const ACTIONS = [
        'create' => 'neu anlegen + zuordnen',
        'attach' => 'zuordnen',
        'exists' => 'bereits zugeordnet',
    ];

    public function handle(SpreadsheetReader $reader, PeopleImport $import): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("Datei nicht gefunden: {$file}");

            return self::FAILURE;
        }

        try {
            $plan = $import->plan($reader->read($file, pathinfo($file, PATHINFO_EXTENSION)));
        } catch (VotingException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $entries = $plan['entries'];
        $errors = $plan['errors'];

        if ($entries !== []) {
            $this->table(
                ['Zeile', 'Mitglied', 'E-Mail', 'Name', 'Aktion'],
                array_map(fn ($e) => [$e['line'], $e['club'], $e['email'], $e['name'], self::ACTIONS[$e['action']]], $entries),
            );
        }

        $counts = array_count_values(array_column($entries, 'action'));
        $this->components->twoColumnDetail('Neue Personen', (string) ($counts['create'] ?? 0));
        $this->components->twoColumnDetail('Zuordnungen zu vorhandenen Personen', (string) ($counts['attach'] ?? 0));
        $this->components->twoColumnDetail('Bereits vorhanden', (string) ($counts['exists'] ?? 0));
        $this->components->twoColumnDetail('Fehlerhafte Zeilen', (string) count($errors));

        if ($errors !== []) {
            $this->newLine();
            $this->table(['Zeile', 'Fehler'], array_map(fn ($e) => [$e['line'], $e['message']], $errors));

            if (! $this->option('skip-invalid')) {
                $this->components->error('Abgebrochen. Datei korrigieren oder mit --skip-invalid nur die gültigen Zeilen importieren.');

                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            $this->components->info('Probelauf – es wurde nichts geändert.');

            return self::SUCCESS;
        }

        if (($counts['create'] ?? 0) + ($counts['attach'] ?? 0) === 0) {
            $this->components->info('Nichts zu tun.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Import ausführen?')) {
            $this->components->warn('Abgebrochen.');

            return self::FAILURE;
        }

        $result = $import->apply($entries);
        $this->components->info(count($result['created']).' Personen angelegt, '.$result['attached'].' Zuordnungen ergänzt.');

        if ($this->option('invite')) {
            $this->invite($result['created']);
        } elseif ($result['created'] !== []) {
            $this->components->warn('Keine Einladungen verschickt. Das geht später in der Verwaltung: Personen markieren → „Einladung senden“.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<User>  $users
     */
    private function invite(array $users): void
    {
        $sent = 0;

        foreach ($users as $user) {
            try {
                if (Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT) {
                    $sent++;
                } else {
                    $this->components->warn("Einladung an {$user->email} nicht verschickt.");
                }
            } catch (Throwable $e) {
                report($e);
                $this->components->error("Einladung an {$user->email} fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $this->components->info("{$sent} Einladungen verschickt.");
    }
}
