<?php

namespace Database\Seeders;

use App\Enums\PollMode;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use Illuminate\Database\Seeder;

/**
 * Demo-Daten für die lokale Entwicklung. Alle Passwörter: "password".
 *
 * - admin@example.com    Systemadmin
 * - leitung@example.com  Verwalter:in
 * - <verein>@example.com je eine Vereinsvertretung (z.B. nord@example.com)
 * - praesidium@example.com, vize@example.com je ein Präsidiumsposten
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

        $votingMembers = collect([
            'nord' => ['Fechtschule Nord', 132],
            'sued' => ['Schwertkampf Süd e.V.', 48],
            'ost' => ['Fechtgilde Ost', 75],
            'west' => ['HEMA West', 210],
            'mitte' => ['Historisches Fechten Mitte', 19],
            'kueste' => ['Küstenfechter', 51],
        ])->map(function (array $data, string $key) {
            [$name, $members] = $data;
            $votingMember = VotingMember::factory()->create(['name' => $name, 'member_count' => $members]);
            User::factory()->forVotingMember($votingMember)->create(['name' => "Vertretung {$name}", 'email' => "{$key}@example.com"]);

            return $votingMember;
        });

        // Die Präsidiumsposten legt die Migration an; zwei davon bekommen hier eine Person
        $board = collect([
            'praesidium' => ['Präsident:in', 'Präsidium Beispiel'],
            'vize' => ['Vizepräsident:in Sport und Sportentwicklung', 'Vize Beispiel'],
        ])->map(function (array $data, string $key) {
            [$name, $person] = $data;
            $post = VotingMember::query()->boardMembers()->where('name', $name)->firstOrFail();
            User::factory()->forVotingMember($post)->create(['name' => $person, 'email' => "{$key}@example.com"]);

            return $post;
        });

        // Eine Person, die zwei Vereine vertritt (Vollmacht)
        User::factory()->forVotingMember($votingMembers['ost'])->create(['name' => 'Vollmacht Ost/Mitte', 'email' => 'vollmacht@example.com'])
            ->votingMembers()->attach($votingMembers['mitte']);

        $assembly = Assembly::factory()->create(['title' => 'Mitgliederversammlung '.now()->year, 'date' => now()]);
        $lifecycle = app(AssemblyLifecycle::class);
        $lifecycle->open($assembly);
        $votingMembers->except('kueste')->concat($board)->each(fn (VotingMember $votingMember) => $lifecycle->checkIn($assembly, $votingMember));

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
