<?php

use App\Actions\CastVote;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Filament\Resources\Assemblies\AssemblyResource;
use App\Filament\Resources\Assemblies\Pages\ConductAssembly;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->nord = Club::factory()->members(120)->create(['name' => 'Nord']);
    $this->sued = Club::factory()->members(10)->create(['name' => 'Süd']);
    $this->assembly = Assembly::factory()->create(['title' => 'MV 2027']);
    $this->poll = Poll::factory()->create(['assembly_id' => $this->assembly->id, 'title' => 'Antrag A']);

    $this->actingAs($this->manager);
});

function conduct(Assembly $assembly)
{
    return Livewire::test(ConductAssembly::class, ['record' => $assembly->getRouteKey()]);
}

it('ist nur für Verwaltende erreichbar', function () {
    $this->get(AssemblyResource::getUrl('conduct', ['record' => $this->assembly]))->assertOk()->assertSee('Versammlungsleitung');

    $this->actingAs(User::factory()->create())
        ->get(AssemblyResource::getUrl('conduct', ['record' => $this->assembly]))
        ->assertForbidden();
});

it('steuert eine komplette Versammlung', function () {
    $page = conduct($this->assembly)
        ->callAction('openAssembly')
        ->call('checkIn', $this->nord->id)
        ->call('checkIn', $this->sued->id)
        ->assertSeeText('Eingecheckt: 2 Vereine mit 4 Stimmen');

    expect($this->assembly->fresh()->status)->toBe(AssemblyStatus::Open);

    $page->call('openPoll', $this->poll->id)
        ->assertSee('0 / 2')
        ->assertSee('Das Ergebnis wird erst nach dem Schließen angezeigt.');

    $rep = User::factory()->forClub($this->nord)->create();
    $position = $this->poll->positions()->first();
    app(CastVote::class)->handle($rep, $this->nord, $this->poll, [
        $position->id => $position->options()->where('label', 'Dafür')->value('id'),
    ]);

    $page->call('$refresh')
        ->assertSee('1 / 2')
        ->assertSee('3 / 4')
        ->assertSee('Noch nicht abgestimmt (1)')
        ->assertDontSee('Ergebnis</h3>', false);

    $page->call('closePoll', $this->poll->id)
        ->assertSee('Ergebnis')
        ->assertSee('100,0 %');
    expect($this->poll->fresh()->status)->toBe(PollStatus::Closed);

    $page->call('publishPoll', $this->poll->id);
    expect($this->poll->fresh()->status)->toBe(PollStatus::Published);

    $page->callAction('closeAssembly');
    expect($this->assembly->fresh()->status)->toBe(AssemblyStatus::Closed);
});

it('meldet fachliche Fehler, statt abzubrechen', function () {
    // Versammlung noch nicht eröffnet
    conduct($this->assembly)
        ->call('openPoll', $this->poll->id)
        ->assertNotified('Die Versammlung muss zuerst eröffnet werden.');

    expect($this->poll->fresh()->status)->toBe(PollStatus::Draft);
});

it('checkt aus und setzt Abstimmungen zurück', function () {
    $this->assembly->update(['status' => AssemblyStatus::Open]);

    conduct($this->assembly)
        ->call('checkIn', $this->nord->id)
        ->call('checkOut', $this->nord->id)
        ->call('openPoll', $this->poll->id)
        ->call('resetPoll', $this->poll->id);

    expect($this->assembly->clubs()->count())->toBe(0)
        ->and($this->poll->fresh()->status)->toBe(PollStatus::Draft);
});

it('lässt in beendeten Versammlungen nichts mehr ändern', function () {
    $this->assembly->update(['status' => AssemblyStatus::Closed]);

    conduct($this->assembly)
        ->call('checkIn', $this->nord->id)
        ->assertNotified('Keine Berechtigung oder Versammlung beendet.');

    expect($this->assembly->clubs()->count())->toBe(0);
});

it('ignoriert Abstimmungen anderer Versammlungen', function () {
    $foreign = Poll::factory()->create();

    conduct($this->assembly)->call('openPoll', $foreign->id)->assertNotFound();

    expect($foreign->fresh()->status)->toBe(PollStatus::Draft);
});
