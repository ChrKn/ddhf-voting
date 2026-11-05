<div wire:poll.{{ config('voting.poll_interval') }}.keep-alive class="space-y-5">
    {{-- Auswahl, wenn eine Person mehrere Mitglieder vertritt --}}
    @if ($clubs->isEmpty())
        <div class="card text-center">
            <p>Deinem Konto ist noch kein Mitglied zugeordnet. Bitte wende dich an die Versammlungsleitung.</p>
        </div>
    @elseif ($clubs->count() > 1)
        <div class="card">
            <p class="label">Ich stimme ab für</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($clubs as $c)
                    <button type="button" wire:click="selectClub({{ $c->id }})"
                        @class(['btn' => $c->id === $clubId, 'btn-secondary' => $c->id !== $clubId])>
                        {{ $c->name }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->club)
        <section class="card">
            <h2 class="text-lg font-semibold">{{ $this->club->name }}</h2>

            @if (! $this->assembly)
                <p class="mt-2">Aktuell läuft keine Versammlung.</p>
            @elseif ($this->votes === null)
                <p class="mt-2">
                    Zur <strong>{{ $this->assembly->title }}</strong> ist {{ $this->club->name }} noch nicht eingecheckt.
                    Bitte melde dich bei der Versammlungsleitung.
                </p>
            @else
                <p class="mt-2 text-neutral-600">
                    {{ $this->assembly->title }} ·
                    <strong class="text-neutral-900">{{ $this->votes }} {{ $this->votes === 1 ? 'Stimme' : 'Stimmen' }}</strong>
                </p>
            @endif
        </section>

        @if ($this->assembly && $this->votes !== null)
            <section class="card" aria-live="polite">
                @if (! $this->poll)
                    <p class="text-center"><strong>Aktuell keine Abstimmung aktiv.</strong></p>
                    <p class="mt-1 text-center text-sm text-neutral-500">Die Seite aktualisiert sich automatisch.</p>
                @else
                    @php($poll = $this->poll)
                    <div class="mb-4">
                        <span class="inline-block rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-neutral-600">
                            {{ $poll->type->label() }} · {{ $poll->mode->label() }}
                        </span>
                        <h2 class="mt-2 text-xl font-bold">{{ $poll->title }}</h2>
                        @if ($poll->description)
                            <p class="mt-2 whitespace-pre-line text-neutral-700">{{ $poll->description }}</p>
                        @endif
                        <p class="mt-3 text-sm text-neutral-600">
                            @if ($poll->isPublic())
                                Öffentlicher Wahlmodus: Die Wahl wird zusammen mit dem Namen des Mitglieds gespeichert und im Ergebnis angezeigt.
                            @else
                                Geheimer Wahlmodus: Es wird nur gespeichert, dass {{ $this->club->name }} abgestimmt hat – nicht, wie.
                            @endif
                        </p>
                    </div>

                    @if ($error)
                        <div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $error }}</div>
                    @endif

                    @if ($this->participation)
                        <div class="rounded border border-green-200 bg-green-50 p-4">
                            <p class="font-semibold text-green-900">{{ $this->club->name }} hat abgestimmt.</p>
                            @if ($poll->isPublic())
                                <dl class="mt-2 space-y-1 text-sm">
                                    @foreach ($this->ownChoices() as $position => $label)
                                        <div><dt class="inline font-medium">{{ $position }}:</dt> <dd class="inline">{{ $label }}</dd></div>
                                    @endforeach
                                </dl>
                            @else
                                <p class="mt-1 text-sm text-green-900">Sollte dabei ein Fehler passiert sein, melde dich bitte bei der Versammlungsleitung. Die Abstimmung muss dann wiederholt werden.</p>
                            @endif
                        </div>
                    @elseif ($confirming)
                        <div class="space-y-3">
                            <p class="font-semibold">Bitte prüfe deine Auswahl:</p>
                            <dl class="divide-y divide-neutral-200 rounded border border-neutral-200">
                                @foreach ($poll->positions as $position)
                                    <div class="flex justify-between gap-4 px-3 py-2">
                                        <dt class="text-neutral-600">{{ $position->title }}</dt>
                                        <dd class="font-semibold">{{ $position->options->firstWhere('id', (int) ($choices[$position->id] ?? 0))?->label }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                            <p class="text-sm text-neutral-600">Die Stimme kann danach nicht mehr geändert werden. Es zählen alle {{ $this->votes }} Stimmen von {{ $this->club->name }}.</p>
                            <div class="flex gap-3">
                                <button type="button" wire:click="back" class="btn-secondary">Zurück</button>
                                <button type="button" wire:click="submit" wire:loading.attr="disabled" class="btn flex-1">Verbindlich abstimmen</button>
                            </div>
                        </div>
                    @else
                        <form wire:submit="review" class="space-y-5">
                            @foreach ($poll->positions as $position)
                                <fieldset>
                                    @if ($poll->positions->count() > 1 || $position->title !== $poll->title)
                                        <legend class="mb-2 font-semibold">{{ $position->title }}</legend>
                                    @endif
                                    <div class="space-y-2">
                                        @foreach ($position->options as $option)
                                            <label class="flex cursor-pointer items-center gap-3 rounded border border-neutral-200 px-3 py-2 hover:bg-neutral-50 has-[:checked]:border-ddhf has-[:checked]:bg-red-50">
                                                <input type="radio" wire:model="choices.{{ $position->id }}" value="{{ $option->id }}" class="text-ddhf focus:ring-ddhf">
                                                <span @class(['italic text-neutral-600' => $option->is_abstention])>
                                                    {{ $option->label }}
                                                    @if ($option->club_hint)
                                                        <span class="text-sm text-neutral-500">({{ $option->club_hint }})</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                            <button type="submit" class="btn w-full">Weiter</button>
                        </form>
                    @endif
                @endif
            </section>

            @if ($publishedResult && $this->published)
                <section class="card">
                    <h2 class="font-semibold">Letztes Ergebnis: {{ $this->published->title }}</h2>
                    @include('partials.result', ['positions' => $publishedResult, 'showTitles' => $this->published->positions->count() > 1])
                </section>
            @endif
        @endif
    @endif
</div>
