<?php

namespace App\Livewire;

use App\Enums\PollStatus;
use App\Models\Assembly;
use App\Services\PollResult;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Beamer-Ansicht einer Versammlung, erreichbar ohne Login über den geheimen
 * Link /screen/{public_token}.
 *
 * Gezeigt wird die offene Abstimmung mit Fortschritt (ohne Vereinsnamen),
 * sonst die zuletzt beendete – mit Ergebnis erst nach der Freigabe.
 */
#[Layout('layouts::screen')]
class ResultScreen extends Component
{
    #[Locked]
    public int $assemblyId;

    public function mount(string $token): void
    {
        $this->assemblyId = Assembly::query()->where('public_token', $token)->firstOrFail()->id;
    }

    public function render(): View
    {
        $assembly = Assembly::findOrFail($this->assemblyId);

        $poll = $assembly->polls()->where('status', PollStatus::Open)->first()
            ?? $assembly->polls()
                ->whereIn('status', [PollStatus::Closed, PollStatus::Published])
                ->reorder()
                ->latest('closed_at')
                ->latest('id')
                ->first();

        $result = $poll ? PollResult::for($poll) : null;

        return view('livewire.result-screen', [
            'assembly' => $assembly,
            'poll' => $poll,
            'progress' => $result?->progress(),
            'results' => $poll?->status === PollStatus::Published ? $result?->positions() : null,
        ])->title($assembly->title);
    }
}
