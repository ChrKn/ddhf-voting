<?php

namespace App\Livewire;

use App\Actions\CastVote;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\User;
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
 * @property-read Club|null $club
 * @property-read Assembly|null $assembly
 * @property-read int|null $votes
 * @property-read Poll|null $poll
 * @property-read PollParticipation|null $participation
 * @property-read Poll|null $published
 */
#[Title('Abstimmung')]
class Ballot extends Component
{
    #[Session]
    public ?int $clubId = null;

    /** @var array<int|string, int|string|null> position_id => option_id */
    public array $choices = [];

    /** Für welche Abstimmung $choices gilt */
    public ?int $choicesForPoll = null;

    public bool $confirming = false;

    public ?string $error = null;

    public function mount(): void
    {
        $clubs = $this->clubs();

        if ($this->clubId === null || ! $clubs->contains('id', $this->clubId)) {
            $this->clubId = $clubs->count() === 1 ? $clubs->first()->id : null;
        }
    }

    public function selectClub(int $clubId): void
    {
        if ($this->clubs()->contains('id', $clubId)) {
            $this->clubId = $clubId;
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
        $club = $this->club;
        $poll = $this->poll;

        if ($club === null || $poll === null) {
            $this->error = 'Die Abstimmung ist nicht mehr offen.';
            $this->confirming = false;

            return;
        }

        try {
            $castVote->handle($this->user(), $club, $poll, $this->choices);
        } catch (VotingException $e) {
            $this->error = $e->getMessage();
        }

        $this->confirming = false;
        unset($this->participation);
    }

    #[Computed]
    public function club(): ?Club
    {
        return $this->clubId ? $this->clubs()->firstWhere('id', $this->clubId) : null;
    }

    /**
     * Laufende Versammlung, zu der der gewählte Verein eingecheckt ist –
     * sonst irgendeine laufende Versammlung (für den Hinweis „nicht eingecheckt“).
     */
    #[Computed]
    public function assembly(): ?Assembly
    {
        $open = Assembly::query()->where('status', AssemblyStatus::Open)->latest('date')->latest('id');

        if ($this->club !== null) {
            $checkedIn = (clone $open)->whereHas('clubs', fn ($q) => $q->whereKey($this->club->id))->first();
            if ($checkedIn !== null) {
                return $checkedIn;
            }
        }

        return $open->first();
    }

    #[Computed]
    public function votes(): ?int
    {
        return ($this->assembly && $this->club)
            ? app(AssemblyLifecycle::class)->votesOf($this->assembly, $this->club)
            : null;
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
        return ($this->poll && $this->club)
            ? $this->poll->participations()->where('club_id', $this->club->id)->first()
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

        return view('livewire.ballot', [
            'clubs' => $this->clubs(),
            'publishedResult' => $this->published ? PollResult::for($this->published)->positions() : null,
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
     * @return Collection<int, Club>
     */
    private function clubs(): Collection
    {
        return $this->user()->clubs()->orderBy('name')->get();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
