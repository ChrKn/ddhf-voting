<?php

namespace App\Filament\Resources\Assemblies\Pages;

use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Filament\Resources\Assemblies\AssemblyResource;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use App\Services\PollResult;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Versammlungsleitung: Check-in, Abstimmungen öffnen/schließen/freigeben,
 * Fortschritt und Ergebnisse. Aktualisiert sich per Polling.
 *
 * Solange eine Abstimmung offen ist, wird bewusst kein Zwischenergebnis
 * angezeigt: Wer die Zähler live beobachtet, könnte sonst einzelne Abgaben
 * Vereinen zuordnen.
 */
class ConductAssembly extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AssemblyResource::class;

    protected string $view = 'filament.resources.assemblies.pages.conduct-assembly';

    public ?int $selectedPollId = null;

    public string $clubSearch = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless($this->user()->can('view', $this->assembly()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Versammlungsleitung: '.$this->assembly()->title;
    }

    public static function getNavigationLabel(): string
    {
        return 'Versammlungsleitung';
    }

    protected function getHeaderActions(): array
    {
        $assembly = $this->assembly();

        return [
            Action::make('screen')
                ->label('Beamer-Ansicht')
                ->icon(Heroicon::OutlinedTv)
                ->color('gray')
                ->url(fn () => route('screen', $assembly->public_token), shouldOpenInNewTab: true),
            Action::make('openAssembly')
                ->label('Versammlung eröffnen')
                ->icon(Heroicon::OutlinedPlay)
                ->visible($assembly->status === AssemblyStatus::Draft)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn () => app(AssemblyLifecycle::class)->open($this->assembly()), 'Versammlung eröffnet')),
            Action::make('closeAssembly')
                ->label('Versammlung beenden')
                ->icon(Heroicon::OutlinedStop)
                ->color('danger')
                ->visible($assembly->status === AssemblyStatus::Open)
                ->requiresConfirmation()
                ->modalDescription('Danach sind keine Abstimmungen und kein Check-in mehr möglich.')
                ->action(fn () => $this->run(fn () => app(AssemblyLifecycle::class)->close($this->assembly()), 'Versammlung beendet')),
        ];
    }

    // ---------------------------------------------------------------- Check-in

    public function checkIn(int $clubId): void
    {
        $club = Club::findOrFail($clubId);
        $this->run(fn () => app(AssemblyLifecycle::class)->checkIn($this->assembly(), $club), "{$club->name} eingecheckt");
    }

    public function checkOut(int $clubId): void
    {
        $club = Club::findOrFail($clubId);
        $this->run(fn () => app(AssemblyLifecycle::class)->checkOut($this->assembly(), $club), "{$club->name} ausgecheckt");
    }

    // ------------------------------------------------------------ Abstimmungen

    public function selectPoll(int $pollId): void
    {
        $this->selectedPollId = $this->findPoll($pollId)->id;
    }

    public function openPoll(int $pollId): void
    {
        $poll = $this->findPoll($pollId);
        $this->run(fn () => app(PollLifecycle::class)->open($poll), 'Abstimmung geöffnet');
        $this->selectedPollId = $poll->id;
    }

    public function closePoll(int $pollId): void
    {
        $poll = $this->findPoll($pollId);
        $this->run(fn () => app(PollLifecycle::class)->close($poll), 'Abstimmung geschlossen');
        $this->selectedPollId = $poll->id;
    }

    public function publishPoll(int $pollId): void
    {
        $poll = $this->findPoll($pollId);
        $this->run(fn () => app(PollLifecycle::class)->publish($poll), 'Ergebnis veröffentlicht');
    }

    public function resetPoll(int $pollId): void
    {
        $poll = $this->findPoll($pollId);
        $this->run(fn () => app(PollLifecycle::class)->reset($poll, $this->user()), 'Abstimmung zurückgesetzt, alle Stimmen verworfen');
    }

    // ------------------------------------------------------------------ Daten

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $assembly = $this->assembly();
        $polls = $assembly->polls()->withCount('participations')->get();
        $poll = $this->currentPoll($polls);
        $result = $poll ? PollResult::for($poll) : null;
        $showResults = $poll !== null && in_array($poll->status, [PollStatus::Closed, PollStatus::Published], true);

        return [
            'assembly' => $assembly,
            'canConduct' => $this->user()->can('conduct', $assembly),
            'attendance' => $this->attendance(),
            'totalVotes' => $assembly->totalVotes(),
            'checkedInCount' => $assembly->clubs()->count(),
            'polls' => $polls,
            'openPollCount' => $polls->where('status', PollStatus::Open)->count(),
            'poll' => $poll,
            'progress' => $result?->progress(),
            'pending' => $result?->pendingClubs() ?? collect(),
            'results' => $showResults ? $result?->positions() : null,
            'details' => $showResults ? $result?->details() : [],
        ];
    }

    /**
     * Alle aktiven Vereine (plus eingecheckte inaktive) mit Check-in-Status.
     *
     * @return Collection<int, array{club: Club, votes: ?int, checked_in_at: ?Carbon}>
     */
    private function attendance(): Collection
    {
        $checkedIn = $this->assembly()->clubs()->get()->keyBy('id');

        return Club::query()
            ->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $checkedIn->keys()))
            ->when($this->clubSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->clubSearch.'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club) => [
                'club' => $club,
                'votes' => $checkedIn->get($club->id)?->attendance->votes,
                'checked_in_at' => $checkedIn->get($club->id)?->attendance->checked_in_at,
            ]);
    }

    /**
     * Gewählte Abstimmung, sonst die offene, sonst die zuletzt bearbeitete.
     *
     * @param  Collection<int, Poll>  $polls
     */
    private function currentPoll(Collection $polls): ?Poll
    {
        return $polls->firstWhere('id', $this->selectedPollId)
            ?? $polls->firstWhere('status', PollStatus::Open)
            ?? $polls->whereIn('status', [PollStatus::Closed, PollStatus::Published])->sortByDesc('closed_at')->first();
    }

    private function findPoll(int $pollId): Poll
    {
        return $this->assembly()->polls()->findOrFail($pollId);
    }

    private function run(callable $operation, string $success): void
    {
        if (! $this->user()->can('conduct', $this->assembly())) {
            Notification::make()->title('Keine Berechtigung oder Versammlung beendet.')->danger()->send();

            return;
        }

        try {
            $operation();
            Notification::make()->title($success)->success()->send();
        } catch (VotingException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->record->refresh();
    }

    private function assembly(): Assembly
    {
        /** @var Assembly */
        return $this->record;
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
