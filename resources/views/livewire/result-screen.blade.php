<div wire:poll.{{ config('voting.poll_interval') }}.keep-alive class="text-2xl">
    @if (! $poll)
        <p class="mt-24 text-center text-4xl font-semibold text-neutral-500">Aktuell keine Abstimmung.</p>
    @else
        <div class="mb-2 text-base font-medium uppercase tracking-wider text-neutral-500">
            {{ $poll->type->label() }} · {{ $poll->mode->label() }}
        </div>
        <h1 class="text-5xl leading-tight font-bold">{{ $poll->title }}</h1>

        @if ($poll->status === \App\Enums\PollStatus::Open)
            @php($share = $progress['votes_present'] > 0 ? round($progress['votes_cast'] / $progress['votes_present'] * 100) : 0)
            <div class="mt-12">
                <div class="flex items-baseline justify-between">
                    <span class="text-3xl font-semibold text-green-700">Abstimmung läuft</span>
                    <span class="tabular-nums text-neutral-600">
                        {{ $progress['clubs_voted'] }} von {{ $progress['clubs_present'] }} Mitgliedern
                        · {{ $progress['votes_cast'] }} von {{ $progress['votes_present'] }} Stimmen
                    </span>
                </div>
                <div class="mt-4 h-6 overflow-hidden rounded-full bg-neutral-100">
                    <div class="h-full rounded-full transition-all duration-500" style="width: {{ $share }}%; background-color: #cc1d1b"></div>
                </div>
            </div>
        @elseif ($poll->status === \App\Enums\PollStatus::Closed)
            <p class="mt-12 text-3xl font-semibold text-neutral-600">Abstimmung beendet – das Ergebnis wird gleich bekannt gegeben.</p>
            <p class="mt-3 tabular-nums text-neutral-500">{{ $progress['votes_cast'] }} von {{ $progress['votes_present'] }} Stimmen abgegeben.</p>
        @elseif ($results)
            <div class="mt-10 [&_h3]:text-3xl [&_li]:text-2xl [&_.text-sm]:text-2xl [&_.h-2]:h-5 [&_.text-xs]:text-lg">
                @include('partials.result', ['positions' => $results, 'showTitles' => count($results) > 1 || $results[0]['title'] !== $poll->title])
            </div>
        @endif
    @endif
</div>
