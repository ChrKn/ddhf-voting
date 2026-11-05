<?php

namespace Database\Seeders;

use App\Enums\PollMode;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;
use App\Services\AssemblyLifecycle;
use Illuminate\Database\Seeder;

/**
 * Demo-Daten für die lokale Entwicklung. Alle Passwörter: "password".
 *
 * - admin@example.com    Systemadmin
 * - leitung@example.com  Verwalter:in
 * - <verein>@example.com je eine Vereinsvertretung (z.B. nord@example.com)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Demo-Daten werden in Produktion nicht angelegt. Admin mit "php artisan voting:create-admin" anlegen.');

            return;
        }

        User::factory()->systemAdmin()->create(['name' => 'Systemadmin', 'email' => 'admin@example.com']);
        User::factory()->manager()->create(['name' => 'Versammlungsleitung', 'email' => 'leitung@example.com']);

        $clubs = collect([
            'nord' => ['Fechtschule Nord', 132],
            'sued' => ['Schwertkampf Süd e.V.', 48],
            'ost' => ['Fechtgilde Ost', 75],
            'west' => ['HEMA West', 210],
            'mitte' => ['Historisches Fechten Mitte', 19],
            'kueste' => ['Küstenfechter', 51],
        ])->map(function (array $data, string $key) {
            [$name, $members] = $data;
            $club = Club::factory()->create(['name' => $name, 'member_count' => $members]);
            User::factory()->forClub($club)->create(['name' => "Vertretung {$name}", 'email' => "{$key}@example.com"]);

            return $club;
        });

        // Eine Person, die zwei Vereine vertritt (Vollmacht)
        User::factory()->forClub($clubs['ost'])->create(['name' => 'Vollmacht Ost/Mitte', 'email' => 'vollmacht@example.com'])
            ->clubs()->attach($clubs['mitte']);

        $assembly = Assembly::factory()->create(['title' => 'Mitgliederversammlung '.now()->year, 'date' => now()]);
        $lifecycle = app(AssemblyLifecycle::class);
        $lifecycle->open($assembly);
        $clubs->except('kueste')->each(fn (Club $club) => $lifecycle->checkIn($assembly, $club));

        Poll::factory()->create([
            'assembly_id' => $assembly->id,
            'title' => 'Genehmigung des Protokolls der letzten Versammlung',
            'mode' => PollMode::Public,
            'sort' => 1,
        ]);

        Poll::factory()->create([
            'assembly_id' => $assembly->id,
            'title' => 'Antrag auf Anpassung des Mitgliedsbeitrags',
            'description' => "Der Vorstand beantragt, den Jahresbeitrag ab dem kommenden Jahr anzupassen.\nBegründung siehe Einladung.",
            'sort' => 2,
        ]);

        Poll::factory()->election([
            'Präsident:in' => ['Alex Beispiel', 'Robin Muster'],
            'Vizepräsident:in Sport und Sportentwicklung' => ['Kim Klinge'],
            'Vizepräsident:in Forschung und Bildung' => ['Sam Schrift'],
            'Vizepräsident:in Wirtschaft und Finanzen' => ['Jo Kasse'],
            'Aktivenvertreter:in' => ['Mika Parade', 'Lou Finte'],
            'Jugendbeauftragte:r' => ['Noa Junior'],
            'Gleichstellungsbeauftragte:r' => ['Eli Gleich'],
        ])->create([
            'assembly_id' => $assembly->id,
            'title' => 'Präsidiumswahl',
            'sort' => 3,
        ]);
    }
}
