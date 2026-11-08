<?php

use App\Actions\CastVote;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;

beforeEach(function () {
    $this->assembly = Assembly::factory()->create(['title' => 'MV 2027']);
    app(AssemblyLifecycle::class)->open($this->assembly);
    $this->votingMember = VotingMember::factory()->members(120)->create(['name' => 'Fechtschule Nord']);
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);
    $rep = User::factory()->forVotingMember($this->votingMember)->create();

    foreach (['secret' => 'Geheimer Antrag', 'public' => 'Offener Antrag'] as $mode => $title) {
        $poll = Poll::factory()->create(['assembly_id' => $this->assembly->id, 'title' => $title, 'mode' => $mode]);
        app(PollLifecycle::class)->open($poll);
        $position = $poll->positions()->first();
        app(CastVote::class)->handle($rep, $this->votingMember, $poll, [
            $position->id => $position->options()->where('label', 'Dagegen')->value('id'),
        ]);
        app(PollLifecycle::class)->close($poll);
    }
});

it('exportiert Anwesenheit und Ergebnisse als CSV', function () {
    $response = $this->actingAs(User::factory()->manager()->create())
        ->get(route('assemblies.export', $this->assembly))
        ->assertOk()
        ->assertDownload();

    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->toContain('"Fechtschule Nord";3;')
        ->toContain('Geheimer Antrag')
        ->toContain('Dagegen;3;100,0')
        ->toContain('Detailergebnis (öffentlich)')
        ->toContain('"Fechtschule Nord";3;"Offener Antrag";Dagegen');

    // Der geheime Antrag hat kein Detailergebnis
    expect(substr_count($csv, 'Detailergebnis'))->toBe(1);
});

it('ist nur für Verwaltende', function () {
    $this->actingAs(User::factory()->forVotingMember($this->votingMember)->create())
        ->get(route('assemblies.export', $this->assembly))
        ->assertForbidden();

    auth()->logout();
    $this->get(route('assemblies.export', $this->assembly))->assertRedirect(route('login'));
});
