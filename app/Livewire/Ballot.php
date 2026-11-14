<?php

namespace App\Livewire;

use App\Actions\CastVote;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\AssemblyVotingMember;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use App\Services\PollResult;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Wahlmaske für Vereinsvertreter:innen. Aktualisiert sich per Polling, damit
 * neu geöffnete Abstimmungen ohne Neuladen erscheinen.
 *
 * @property-read VotingMember|null $votingMember
 * @property-read Assembly|null $assembly
 * @property-read AssemblyVotingMember|null $attendance
 * @property-read int|null $votes
 * @property-read Poll|null $poll
 * @property-read PollParticipation|null $participation
 * @property-read Poll|null $published
 */
#[Title('Abstimmung')]
class Ballot extends Component
{
    #[Session]
    public ?int $votingMemberId = null;

    /** @var array<int|string, int|string|null> position_id => option_id */
    public array $choices = [];

    /** Für welche Abstimmung $choices gilt */
    public ?int $choicesForPoll = null;

    public bool $confirming = false;

    public ?string $error = null;

    public function mount(): void
    {
        $votingMembers = $this->votingMembers();

        if ($this->votingMemberId === null || ! $votingMembers->contains('id', $this->votingMemberId)) {
            $this->votingMemberId = $votingMembers->count() === 1 ? $votingMembers->first()->id : null;
        }
    }

    public function selectVotingMember(int $votingMemberId): void
    {
        if ($this->votingMembers()->contains('id', $votingMemberId)) {
            $this->votingMemberId = $votingMemberId;
            $this->resetBallot();
        }
    }

    public function review(): void
    {
        $this->error = null;
        $poll = $this->poll;

        if ($poll === null) {
            return;
        }

        foreach ($poll->positions as $position) {
            if (empty($this->choices[$position->id])) {
                $this->error = "Bitte für „{$position->title}“ eine Auswahl treffen.";

                return;
            }
        }

        $this->confirming = true;
    }

    public function back(): void
    {
        $this->confirming = false;
    }

    public function submit(CastVote $castVote): void
    {
        $this->error = null;
        $votingMember = $this->votingMember;
        $poll = $this->poll;

        if ($votingMember === null || $poll === null) {
            $this->error = 'Die Abstimmung ist nicht mehr offen.';
            $this->confirming = false;

            return;
        }

        try {
            $castVote->handle($this->user(), $votingMember, $poll, $this->choices);
        } catch (VotingException $e) {
            $this->error = $e->getMessage();
        }

        $this->confirming = false;
        unset($this->participation);
    }

    #[Computed]
    public function votingMember(): ?VotingMember
    {
        return $this->votingMemberId ? $this->votingMembers()->firstWhere('id', $this->votingMemberId) : null;
    }

    /**
     * Laufende Versammlung, zu der der gewählte Verein eingecheckt ist –
     * sonst irgendeine laufende Versammlung (für den Hinweis „nicht eingecheckt“).
     */
    #[Computed]
    public function assembly(): ?Assembly
    {
        $open = Assembly::query()->where('status', AssemblyStatus::Open)->latest('date')->latest('id');

        if ($this->votingMember !== null) {
            $checkedIn = (clone $open)->whereHas('votingMembers', fn ($q) => $q->whereKey($this->votingMember->id))->first();
            if ($checkedIn !== null) {
                return $checkedIn;
            }
        }

        return $open->first();
    }

    /** Snapshot vom Check-in, null solange nicht eingecheckt. */
    #[Computed]
    public function attendance(): ?AssemblyVotingMember
    {
        return ($this->assembly && $this->votingMember)
            ? app(AssemblyLifecycle::class)->attendanceOf($this->assembly, $this->votingMember)
            : null;
    }

    #[Computed]
    public function votes(): ?int
    {
        return $this->attendance?->votes;
    }

    #[Computed]
    public function poll(): ?Poll
    {
        return $this->assembly?->polls()
            ->where('status', PollStatus::Open)
            ->with('positions.options')
            ->first();
    }

    #[Computed]
    public function participation(): ?PollParticipation
    {
        return ($this->poll && $this->votingMember)
            ? $this->poll->participations()->where('voting_member_id', $this->votingMember->id)->first()
            : null;
    }

    /** Zuletzt veröffentlichtes Ergebnis der Versammlung */
    #[Computed]
    public function published(): ?Poll
    {
        return $this->assembly?->polls()
            ->where('status', PollStatus::Published)
            ->reorder()
            ->latest('closed_at')
            ->latest('id')
            ->first();
    }

    /**
     * Eigene Auswahl lesbar machen: [Amt => Option]
     *
     * @return array<string, string>
     */
    public function ownChoices(): array
    {
        $poll = $this->poll;
        $choices = $this->participation?->public_choices;

        if ($poll === null || $choices === null) {
            return [];
        }

        $result = [];
        foreach ($poll->positions as $position) {
            $optionId = $choices[$position->id] ?? null;
            $result[$position->title] = (string) $position->options->firstWhere('id', $optionId)?->label;
        }

        return $result;
    }

    public function render(): View
    {
        $this->syncBallotWithPoll();

        $result = $this->published ? PollResult::for($this->published) : null;

        return view('livewire.ballot', [
            'votingMembers' => $this->votingMembers(),
            'publishedResult' => $result?->positions(),
            // Wer wie gestimmt hat, nur bei öffentlichen Abstimmungen (sonst leer)
            'publishedDetails' => $result?->details() ?? [],
        ]);
    }

    /**
     * Neue Abstimmung → Auswahl zurücksetzen und „Enthaltung“ vorbelegen
     * (wie in der bisherigen Version).
     */
    private function syncBallotWithPoll(): void
    {
        $poll = $this->poll;

        if ($poll?->id === $this->choicesForPoll) {
            return;
        }

        $this->resetBallot();
        $this->choicesForPoll = $poll?->id;

        foreach ($poll->positions ?? [] as $position) {
            $abstention = $position->options->firstWhere('is_abstention', true);
            $this->choices[$position->id] = $abstention?->id;
        }
    }

    private function resetBallot(): void
    {
        $this->choices = [];
        $this->choicesForPoll = null;
        $this->confirming = false;
        $this->error = null;
    }

    /**
     * @return Collection<int, VotingMember>
     */
    private function votingMembers(): Collection
    {
        return $this->user()->votingMembers()->orderBy('name')->get();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
