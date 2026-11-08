<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\VotingMember;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function peopleCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'ppl').'.csv';
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    $this->nord = VotingMember::factory()->create(['name' => 'Fechtschule Nord']);
    $this->sued = VotingMember::factory()->create(['name' => 'Schwertkampf Süd e.V.']);
});

it('legt Personen an und ordnet sie zu, auch mehreren Mitgliedern', function () {
    $file = peopleCsv(
        "Mitglied;E-Mail;Name\n".
        "Fechtschule Nord;Anna@Example.com;Anna Muster\n".
        "fechtschule nord;bert@example.com;Bert Beispiel\n".
        "Schwertkampf Süd e.V.;anna@example.com;Anna Muster\n"
    );

    $this->artisan('voting:import-people', ['file' => $file, '--force' => true])->assertSuccessful();

    $anna = User::firstWhere('email', 'anna@example.com');
    expect(User::count())->toBe(2)
        ->and($anna->role)->toBe(Role::Member)
        ->and($anna->votingMembers->pluck('name')->sort()->values()->all())->toBe(['Fechtschule Nord', 'Schwertkampf Süd e.V.'])
        ->and($anna->email_verified_at)->toBeNull();
});

it('akzeptiert Spalten in beliebiger Reihenfolge und „Verein“ statt „Mitglied“', function () {
    $file = peopleCsv("Name,Verein,E-Mail\nCem,Fechtschule Nord,cem@example.com\n");

    $this->artisan('voting:import-people', ['file' => $file, '--force' => true])->assertSuccessful();

    expect(User::firstWhere('email', 'cem@example.com')->represents($this->nord))->toBeTrue();
});

it('ordnet Personen auch Präsidiumsposten zu, Spalte „Posten“', function () {
    $post = VotingMember::firstWhere('name', 'Präsident:in'); // aus der Migration
    $file = peopleCsv('Posten;E-Mail;Name
Präsident:in;praesi@example.com;Pia Präsi
');

    $this->artisan('voting:import-people', ['file' => $file, '--force' => true])->assertSuccessful();

    expect(User::firstWhere('email', 'praesi@example.com')->represents($post))->toBeTrue();
});

it('liest Excel-Dateien', function () {
    $path = tempnam(sys_get_temp_dir(), 'ppl').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Mitglied', 'E-Mail', 'Name']));
    $writer->addRow(Row::fromValues(['Fechtschule Nord', 'dora@example.com', 'Dora']));
    $writer->close();

    $this->artisan('voting:import-people', ['file' => $path, '--force' => true])->assertSuccessful();

    expect(User::where('email', 'dora@example.com')->exists())->toBeTrue();
});

it('ordnet vorhandene Personen nur zu, ohne sie zu ändern', function () {
    $manager = User::factory()->manager()->create(['email' => 'leitung@example.com', 'name' => 'Alte Leitung']);

    $file = peopleCsv("Mitglied;E-Mail;Name\nFechtschule Nord;LEITUNG@example.com;Neuer Name\n");
    $this->artisan('voting:import-people', ['file' => $file, '--force' => true])->assertSuccessful();

    $manager->refresh();
    expect($manager->name)->toBe('Alte Leitung')
        ->and($manager->role)->toBe(Role::Manager)
        ->and($manager->represents($this->nord))->toBeTrue();

    // Zweiter Lauf ändert nichts
    $this->artisan('voting:import-people', ['file' => $file, '--force' => true])
        ->expectsOutputToContain('Nichts zu tun')
        ->assertSuccessful();
});

it('bricht bei fehlerhaften Zeilen ab und nennt sie', function () {
    $file = peopleCsv(
        "Mitglied;E-Mail;Name\n".
        "Fechtschule Nordd;a@example.com;A\n".
        "Unbekannt;b@example.com;B\n".
        "Fechtschule Nord;keine-mail;C\n".
        "Fechtschule Nord;d@example.com;\n".
        "Fechtschule Nord;ok@example.com;OK\n"
    );

    $this->artisan('voting:import-people', ['file' => $file, '--force' => true])
        ->expectsOutputToContain('meinst du „Fechtschule Nord“?')
        ->expectsOutputToContain('Mitglied „Unbekannt“ gibt es nicht')
        ->expectsOutputToContain('Ungültige E-Mail-Adresse')
        ->expectsOutputToContain('Name fehlt')
        ->assertFailed();

    expect(User::count())->toBe(0);

    $this->artisan('voting:import-people', ['file' => $file, '--force' => true, '--skip-invalid' => true])->assertSuccessful();
    expect(User::pluck('email')->all())->toBe(['ok@example.com']);
});

it('meldet fehlende Spalten', function () {
    $this->artisan('voting:import-people', ['file' => peopleCsv("Verein;Mail\nX;y@example.com\n"), '--force' => true])
        ->expectsOutputToContain('Spalte(n) fehlen: Name')
        ->assertFailed();
});

it('ändert im Probelauf nichts', function () {
    $file = peopleCsv("Mitglied;E-Mail;Name\nFechtschule Nord;a@example.com;A\n");

    $this->artisan('voting:import-people', ['file' => $file, '--dry-run' => true])
        ->expectsOutputToContain('Probelauf')
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});

it('fragt ohne --force nach', function () {
    $file = peopleCsv("Mitglied;E-Mail;Name\nFechtschule Nord;a@example.com;A\n");

    $this->artisan('voting:import-people', ['file' => $file])
        ->expectsConfirmation('Import ausführen?', 'no')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('verschickt mit --invite Einladungen an neue Personen', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'alt@example.com']);
    $file = peopleCsv("Mitglied;E-Mail;Name\nFechtschule Nord;neu@example.com;Neu\nFechtschule Nord;alt@example.com;Alt\n");

    $this->artisan('voting:import-people', ['file' => $file, '--force' => true, '--invite' => true])
        ->expectsOutputToContain('1 Einladungen verschickt')
        ->assertSuccessful();

    Notification::assertSentTo(User::firstWhere('email', 'neu@example.com'), ResetPassword::class);
    Notification::assertNotSentTo($existing, ResetPassword::class);
});

it('meldet eine fehlende Datei', function () {
    $this->artisan('voting:import-people', ['file' => 'gibt-es-nicht.xlsx'])->assertFailed();
});
