<?php

use App\Exceptions\VotingException;
use App\Filament\Resources\Clubs\ClubResource;
use App\Filament\Resources\Clubs\Pages\ImportClubs;
use App\Models\Club;
use App\Models\User;
use App\Services\MemberImport;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function importFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'imp');
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    $this->import = app(MemberImport::class);
});

describe('Einlesen', function () {
    it('liest CSV mit Semikolon, BOM und Kopfzeile', function () {
        $rows = $this->import->read(importFile("\xEF\xBB\xBFName;Mitglieder\nFechtschule Nord;120\n\nHEMA West;1.210\n"), 'csv');

        expect($rows)->toBe([['Name', 'Mitglieder'], ['Fechtschule Nord', '120'], ['HEMA West', '1.210']])
            ->and($this->import->guessColumns($rows))->toBe(['name' => 0, 'count' => 1, 'header' => true]);
    });

    it('liest Windows-1252 und Komma als Trenner', function () {
        $rows = $this->import->read(importFile(mb_convert_encoding("Anzahl,Verein\n12,Küstenfechter\n", 'Windows-1252', 'UTF-8')), 'csv');

        expect($rows[1])->toBe(['12', 'Küstenfechter'])
            ->and($this->import->guessColumns($rows))->toBe(['name' => 1, 'count' => 0, 'header' => true]);
    });

    it('erkennt Dateien ohne Kopfzeile', function () {
        $rows = $this->import->read(importFile("Fechtschule Nord;120\n"), 'csv');

        expect($this->import->guessColumns($rows))->toBe(['name' => 0, 'count' => 1, 'header' => false]);
    });

    it('liest Excel-Dateien', function () {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Name', 'Anzahl Mitglieder']));
        $writer->addRow(Row::fromValues(['Fechtschule Nord', 120]));
        $writer->close();

        expect($this->import->read($path, 'xlsx'))->toBe([['Name', 'Anzahl Mitglieder'], ['Fechtschule Nord', '120']]);
    });
});

describe('Zuordnung', function () {
    it('schlägt vorhandene Mitglieder per Name vor, unabhängig von Groß-/Kleinschreibung', function () {
        $nord = Club::factory()->create(['name' => 'Fechtschule Nord']);
        Club::factory()->create(['name' => 'Schwertkampf Süd e.V.']);

        $rows = $this->import->rows([
            ['Name', 'Mitglieder'],
            ['fechtschule  nord', '130'],
            ['Schwertkampf Süd', '40'],
            ['Ganz Neu', '5'],
            ['Kaputt', 'viele'],
        ], 0, 1, true);

        expect($rows[0])->toMatchArray(['line' => 2, 'target' => (string) $nord->id, 'count' => 130])
            ->and($rows[1])->toMatchArray(['target' => 'new', 'similar' => 'Schwertkampf Süd e.V.'])
            ->and($rows[2])->toMatchArray(['target' => 'new', 'similar' => null])
            ->and($rows[3])->toMatchArray(['target' => 'skip', 'error' => 'Ungültige Mitgliederzahl „viele“']);
    });
});

describe('Übernahme', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->systemAdmin()->create());
        $this->nord = Club::factory()->members(100)->create(['name' => 'Fechtschule Nord']);
        $this->sued = Club::factory()->members(40)->create(['name' => 'Schwertkampf Süd e.V.']);
    });

    function row(int $line, string $name, ?int $count, string $target, bool $rename = true): array
    {
        return ['line' => $line, 'name' => $name, 'count' => $count, 'error' => null, 'target' => $target, 'rename' => $rename, 'similar' => null];
    }

    it('aktualisiert, benennt um, legt an und überspringt', function () {
        $summary = $this->import->apply([
            row(2, 'Fechtschule Nord', 130, (string) $this->nord->id),
            row(3, 'Schwertkampf Süd', 40, (string) $this->sued->id),
            row(4, 'Ganz Neu', 5, 'new'),
            row(5, 'Egal', 1, 'skip'),
        ]);

        expect($summary)->toBe(['created' => 1, 'updated' => 2, 'renamed' => 1, 'unchanged' => 0, 'skipped' => 1])
            ->and($this->nord->fresh()->member_count)->toBe(130)
            ->and($this->sued->fresh()->name)->toBe('Schwertkampf Süd')
            ->and(Club::firstWhere('name', 'Ganz Neu')->member_count)->toBe(5);
    });

    it('lässt den Namen auf Wunsch unverändert', function () {
        $this->import->apply([row(2, 'Süd', 41, (string) $this->sued->id, rename: false)]);

        expect($this->sued->fresh())->name->toBe('Schwertkampf Süd e.V.')->member_count->toBe(41);
    });

    it('zählt unveränderte Mitglieder', function () {
        expect($this->import->apply([row(2, 'Fechtschule Nord', 100, (string) $this->nord->id)])['unchanged'])->toBe(1);
    });

    it('erlaubt Namenstausch und neuen Eintrag unter altem Namen', function () {
        $this->import->apply([
            row(2, 'Schwertkampf Süd e.V.', 100, (string) $this->nord->id),
            row(3, 'Fechtschule Nord', 40, (string) $this->sued->id),
        ]);

        expect($this->nord->fresh()->name)->toBe('Schwertkampf Süd e.V.')
            ->and($this->sued->fresh()->name)->toBe('Fechtschule Nord');

        $this->import->apply([
            row(2, 'Fechtschule Nord Neu', 40, (string) $this->sued->id),
            row(3, 'Fechtschule Nord', 7, 'new'),
        ]);
        expect(Club::where('name', 'Fechtschule Nord')->value('member_count'))->toBe(7);
    });

    it('lehnt doppelte Zuordnung und doppelte Namen ab, ohne etwas zu ändern', function () {
        expect(fn () => $this->import->apply([
            row(2, 'A', 1, (string) $this->nord->id),
            row(3, 'B', 2, (string) $this->nord->id),
        ]))->toThrow(VotingException::class, 'mehreren Zeilen zugeordnet');

        expect(fn () => $this->import->apply([row(2, 'fechtschule nord', 3, 'new')]))
            ->toThrow(VotingException::class, 'gibt es schon');

        expect(fn () => $this->import->apply([row(2, 'Fechtschule Nord', 3, (string) $this->sued->id)]))
            ->toThrow(VotingException::class, 'mehrfach');

        expect($this->nord->fresh()->member_count)->toBe(100)
            ->and(Club::count())->toBe(2);
    });
});

describe('Seite', function () {
    it('ist nur für den Systemadmin', function () {
        $this->actingAs(User::factory()->manager()->create())
            ->get(ClubResource::getUrl('import'))
            ->assertForbidden();

        $this->actingAs(User::factory()->systemAdmin()->create())
            ->get(ClubResource::getUrl('import'))
            ->assertOk()
            ->assertSee('Mitglieder importieren');
    });

    it('führt durch Upload, Umzuordnung und Import', function () {
        $this->actingAs(User::factory()->systemAdmin()->create());
        $old = Club::factory()->members(10)->create(['name' => 'Alter Name']);

        $file = UploadedFile::fake()->createWithContent('mitglieder.csv', "Name;Mitglieder\nNeuer Name;60\nNeu dazu;3\n");

        $page = Livewire::test(ImportClubs::class)
            ->set('upload', $file)
            ->assertHasNoErrors()
            ->assertSee('Neuer Name')
            ->assertSet('rows.0.target', 'new')
            // Umbenennung: Zeile dem vorhandenen Mitglied zuordnen
            ->set('rows.0.target', (string) $old->id)
            ->assertSee('Namen ändern: „Alter Name“ → „Neuer Name“')
            ->assertSee('10 → ')
            ->call('import')
            ->assertSet('summary.renamed', 1)
            ->assertSet('summary.created', 1)
            ->assertSet('rows', []);

        expect($old->fresh())->name->toBe('Neuer Name')->member_count->toBe(60)
            ->and($old->fresh()->votes)->toBe(2);
    });

    it('zeigt Probleme an, statt zu importieren', function () {
        $this->actingAs(User::factory()->systemAdmin()->create());
        Club::factory()->create(['name' => 'Doppelt']);

        Livewire::test(ImportClubs::class)
            ->set('upload', UploadedFile::fake()->createWithContent('m.csv', "Name;Mitglieder\nDoppelt;5\n"))
            ->set('rows.0.target', 'new')
            ->call('import')
            ->assertSee('Import nicht möglich')
            ->assertSet('summary', null);
    });

    it('lehnt andere Dateitypen ab', function () {
        $this->actingAs(User::factory()->systemAdmin()->create());

        Livewire::test(ImportClubs::class)
            ->set('upload', UploadedFile::fake()->create('bild.png', 10))
            ->assertHasErrors('upload');
    });
});
