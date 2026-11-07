<?php

use App\Enums\AssemblyEventType;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;

beforeEach(function () {
    $this->assemblies = app(AssemblyLifecycle::class);
    $this->polls = app(PollLifecycle::class);
    $this->assembly = Assembly::factory()->create();
    $this->club = Club::factory()->members(120)->create(['name' => 'Fechtschule Nord']);
});

function eventTypes(Assembly $assembly): array
{
    return $assembly->events()->pluck('type')->map(fn (AssemblyEventType $t) => $t->value)->all();
}

it('protokolliert Check-ins und Checkouts mit Stimmen und ausführender Person', function () {
    $manager = User::factory()->manager()->create(['name' => 'Lea Leitung']);
    $this->actingAs($manager);

    $this->assemblies->open($this->assembly);
    $this->assemblies->checkIn($this->assembly, $this->club);
    $this->assemblies->checkOut($this->assembly, $this->club);

    expect(eventTypes($this->assembly))->toBe(['assembly_opened', 'checked_in', 'checked_out']);

    $checkout = $this->assembly->events()->get()->last();
    expect($checkout->subject)->toBe('Fechtschule Nord')
        ->and($checkout->votes)->toBe(3)
        ->and($checkout->user_name)->toBe('Lea Leitung');
});

it('protokolliert doppelte Check-ins und Checkouts ohne Anwesenheit nicht', function () {
    $this->assemblies->open($this->assembly);
    $this->assemblies->checkOut($this->assembly, $this->club);
    $this->assemblies->checkIn($this->assembly, $this->club);
    $this->assemblies->checkIn($this->assembly, $this->club);

    expect(eventTypes($this->assembly))->toBe(['assembly_opened', 'checked_in']);
});

it('protokolliert Start, Ende, Veröffentlichung und Zurücksetzen von Abstimmungen', function () {
    $this->assemblies->open($this->assembly);
    $poll = Poll::factory()->create(['assembly_id' => $this->assembly->id, 'title' => 'Antrag 1']);

    $this->polls->open($poll);
    $this->polls->close($poll);
    $this->polls->reset($poll);
    $this->polls->open($poll);
    $this->polls->close($poll);
    $this->polls->publish($poll);
    $this->assemblies->close($this->assembly);

    expect(eventTypes($this->assembly))->toBe([
        'assembly_opened',
        'poll_opened', 'poll_closed', 'poll_reset',
        'poll_opened', 'poll_closed', 'poll_published',
        'assembly_closed',
    ])->and($this->assembly->events()->where('type', 'poll_opened')->first()->subject)->toBe('Antrag 1');
});

it('behält Namen im Protokoll, auch wenn das Mitglied später umbenannt wird', function () {
    $this->assemblies->open($this->assembly);
    $this->assemblies->checkIn($this->assembly, $this->club);
    $this->actingAs(User::factory()->systemAdmin()->create());
    $this->club->update(['name' => 'Neuer Name']);

    expect($this->assembly->events()->get()->last()->subject)->toBe('Fechtschule Nord');
});

it('verbietet das Ändern von Protokolleinträgen', function () {
    $this->assemblies->open($this->assembly);

    $this->assembly->events()->first()->update(['subject' => 'manipuliert']);
})->throws(LogicException::class);

it('schreibt den Ablauf ins Protokoll', function () {
    $this->actingAs(User::factory()->manager()->create(['name' => 'Lea Leitung']));
    $this->assemblies->open($this->assembly);
    $this->assemblies->checkIn($this->assembly, $this->club);
    $poll = Poll::factory()->create(['assembly_id' => $this->assembly->id, 'title' => 'Antrag 1']);
    $this->polls->open($poll);
    $this->polls->close($poll);
    $this->assemblies->checkOut($this->assembly, $this->club);

    $csv = $this->get(route('assemblies.export', $this->assembly))->streamedContent();

    expect($csv)->toContain('Ablauf')
        ->toContain(';Checkout;"Fechtschule Nord";3;"Lea Leitung";')
        ->toContain(';"Abstimmung gestartet";"Antrag 1";;"Lea Leitung";')
        ->toContain('Gestartet;"'.$poll->refresh()->opened_at->format('d.m.Y H:i:s').'"')
        ->toContain('Beendet;"'.$poll->closed_at->format('d.m.Y H:i:s').'"');
});
