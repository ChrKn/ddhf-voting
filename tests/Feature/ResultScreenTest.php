<?php

use App\Actions\CastVote;
use App\Livewire\ResultScreen;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use Livewire\Livewire;

beforeEach(function () {
    $this->assembly = Assembly::factory()->create(['title' => 'MV 2027']);
    app(AssemblyLifecycle::class)->open($this->assembly);

    $this->club = Club::factory()->members(120)->create(['name' => 'Geheimverein']);
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->club);

    $this->poll = Poll::factory()->create(['assembly_id' => $this->assembly->id, 'title' => 'Antrag Haushalt']);
    app(PollLifecycle::class)->open($this->poll);

    $rep = User::factory()->forClub($this->club)->create();
    $position = $this->poll->positions()->first();
    app(CastVote::class)->handle($rep, $this->club, $this->poll, [
        $position->id => $position->options()->where('label', 'Dafür')->value('id'),
    ]);
});

function screen(Assembly $assembly)
{
    return Livewire::test(ResultScreen::class, ['token' => $assembly->public_token]);
}

it('ist ohne Login über den Token erreichbar', function () {
    $this->get(route('screen', $this->assembly->public_token))->assertOk()->assertSee('MV 2027');
});

it('liefert 404 bei falschem Token', function () {
    $this->get(route('screen', 'falsch'))->assertNotFound();
});

it('zeigt bei offener Abstimmung nur den Fortschritt, keine Namen und kein Ergebnis', function () {
    screen($this->assembly)
        ->assertSee('Antrag Haushalt')
        ->assertSee('Abstimmung läuft')
        ->assertSee('1 von 1 Mitgliedern')
        ->assertDontSee('Geheimverein')
        ->assertDontSee('Dafür');
});

it('zeigt nach dem Schließen noch kein Ergebnis', function () {
    app(PollLifecycle::class)->close($this->poll);

    screen($this->assembly)
        ->assertSee('das Ergebnis wird gleich bekannt gegeben')
        ->assertDontSee('Dafür');
});

it('zeigt das Ergebnis nach der Freigabe', function () {
    app(PollLifecycle::class)->close($this->poll);
    app(PollLifecycle::class)->publish($this->poll);

    screen($this->assembly)
        ->assertSee('Dafür')
        ->assertSee('3 Stimmen')
        ->assertSee('100,0 %');
});
