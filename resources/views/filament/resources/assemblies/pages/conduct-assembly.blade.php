<x-filament-panels::page>
    <div wire:poll.{{ config('voting.poll_interval') }} class="grid gap-6 lg:grid-cols-5">

        {{-- Linke Spalte: Abstimmungen und aktuelle Abstimmung --}}
        <div class="space-y-6 lg:col-span-3">
            <x-filament::section>
                <x-slot name="heading">Status</x-slot>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <div>Versammlung: <x-filament::badge :color="$assembly->status->getColor()" class="inline-flex">{{ $assembly->status->getLabel() }}</x-filament::badge></div>
                    <div>Eingecheckt: <strong>{{ $checkedInCount }}</strong> Stimmberechtigte mit <strong>{{ $totalVotes }}</strong> Stimmen</div>
                </div>
                @if ($assembly->status === \App\Enums\AssemblyStatus::Draft)
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Stimmberechtigte können schon eingecheckt werden. Abstimmungen lassen sich erst öffnen, wenn die Versammlung eröffnet ist.</p>
                @endif
                @if ($openPollCount > 1)
                    <p class="mt-3 rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                        Achtung: Es sind {{ $openPollCount }} Abstimmungen gleichzeitig offen. Die Wahlmaske zeigt nur die erste.
                    </p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Abstimmungen</x-slot>
                <x-slot name="afterHeader">
                    <x-filament::link :href="\App\Filament\Resources\Polls\PollResource::getUrl('create', ['assembly' => $assembly->id])" icon="heroicon-m-plus" size="sm">
                        Neue Abstimmung
                    </x-filament::link>
                </x-slot>

                @if ($polls->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-400">Noch keine Abstimmungen angelegt.</p>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($polls as $p)
                            <li wire:key="poll-{{ $p->id }}" @class([
                                'flex flex-wrap items-center gap-3 py-3',
                                'bg-primary-50/60 -mx-3 px-3 rounded-lg dark:bg-primary-500/10' => $poll?->id === $p->id,
                            ])>
                                <button type="button" wire:click="selectPoll({{ $p->id }})" class="min-w-0 flex-1 text-left">
                                    <span class="block truncate font-medium">{{ $p->title }}</span>
                                    <span class="text-xs text-gray-500">{{ $p->type->getLabel() }} · {{ $p->mode->getLabel() }} · {{ $p->participations_count }} Abgaben</span>
                                </button>
                                <x-filament::badge :color="$p->status->getColor()">{{ $p->status->getLabel() }}</x-filament::badge>

                                @if ($canConduct)
                                    <div class="flex gap-2">
                                        @switch($p->status)
                                            @case(\App\Enums\PollStatus::Draft)
                                                <x-filament::button size="sm" wire:click="openPoll({{ $p->id }})" wire:confirm="Abstimmung „{{ $p->title }}“ jetzt öffnen?">Öffnen</x-filament::button>
                                                @break
                                            @case(\App\Enums\PollStatus::Open)
                                                <x-filament::button size="sm" color="warning" wire:click="closePoll({{ $p->id }})" wire:confirm="Abstimmung schließen? Danach kann niemand mehr abstimmen.">Schließen</x-filament::button>
                                                <x-filament::button size="sm" color="gray" wire:click="resetPoll({{ $p->id }})" wire:confirm="Alle bisher abgegebenen Stimmen verwerfen und Abstimmung zurücksetzen?">Zurücksetzen</x-filament::button>
                                                @break
                                            @case(\App\Enums\PollStatus::Closed)
                                                <x-filament::button size="sm" color="info" wire:click="publishPoll({{ $p->id }})" wire:confirm="Ergebnis für alle sichtbar machen?">Veröffentlichen</x-filament::button>
                                                <x-filament::button size="sm" color="gray" wire:click="resetPoll({{ $p->id }})" wire:confirm="Alle Stimmen verwerfen und Abstimmung zurücksetzen? Das Ergebnis geht verloren.">Zurücksetzen</x-filament::button>
                                                @break
                                        @endswitch
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            @if ($poll)
                <x-filament::section>
                    <x-slot name="heading">{{ $poll->title }}</x-slot>
                    <x-slot name="description">{{ $poll->type->getLabel() }} · {{ $poll->mode->getLabel() }} · {{ $poll->status->getLabel() }}</x-slot>

                    @if ($poll->status !== \App\Enums\PollStatus::Draft)
                        @php
                            $votingMemberShare = $progress['voting_members_present'] > 0 ? round($progress['voting_members_voted'] / $progress['voting_members_present'] * 100) : 0;
                        @endphp
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Stimmberechtigte</div>
                                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $progress['voting_members_voted'] }} / {{ $progress['voting_members_present'] }}</div>
                                <div class="mt-2 h-2 overflow-hidden rounded bg-gray-100 dark:bg-white/10">
                                    <div class="h-full bg-primary-600" style="width: {{ $votingMemberShare }}%"></div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Stimmen</div>
                                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $progress['votes_cast'] }} / {{ $progress['votes_present'] }}</div>
                                <div class="mt-2 text-xs text-gray-500">abgegeben / anwesend</div>
                            </div>
                        </div>

                        @if ($pending->isNotEmpty())
                            <div class="mt-4">
                                <div class="text-sm font-medium">Noch nicht abgestimmt ({{ $pending->count() }}):</div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($pending as $votingMember)
                                        <x-filament::badge color="gray">{{ $votingMember->name }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                        @elseif ($progress['voting_members_present'] > 0)
                            <p class="mt-4 text-sm font-semibold text-success-600">Alle eingecheckten Stimmberechtigten haben abgestimmt.</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">Noch nicht geöffnet.</p>
                    @endif

                    @if ($poll->status === \App\Enums\PollStatus::Open)
                        <p class="mt-4 text-sm text-gray-500">Das Ergebnis wird erst nach dem Schließen angezeigt.</p>
                    @endif

                    @if ($results)
                        <div class="mt-6 border-t border-gray-200 pt-4 dark:border-white/10">
                            <h3 class="font-semibold">Ergebnis</h3>
                            @include('partials.result', ['positions' => $results, 'showTitles' => count($results) > 1 || $results[0]['title'] !== $poll->title])
                        </div>
                    @endif

                    @if ($details)
                        <div class="mt-6 border-t border-gray-200 pt-4 dark:border-white/10">
                            <h3 class="font-semibold">Detailergebnis (öffentlich)</h3>
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-white/10">
                                            <th class="py-2 pr-4">Stimmberechtigte</th>
                                            <th class="py-2 pr-4">Stimmen</th>
                                            <th class="py-2">Abgestimmt mit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($details as $row)
                                            <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                                <td class="py-2 pr-4">{{ $row['votingMember'] }}</td>
                                                <td class="py-2 pr-4 tabular-nums">{{ $row['votes'] }}</td>
                                                <td class="py-2">
                                                    @foreach ($row['choices'] as $position => $label)
                                                        <div>@if (count($row['choices']) > 1)<span class="text-gray-500">{{ $position }}:</span> @endif{{ $label }}</div>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            @endif
        </div>

        {{-- Rechte Spalte: Anwesenheit --}}
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Anwesenheit</x-slot>
                <x-slot name="description">Die Stimmen werden beim Check-in eingefroren. Fehler lassen sich mit dem Stift korrigieren, solange keine Abstimmung offen ist.</x-slot>

                <x-filament::input.wrapper class="mb-3">
                    <x-filament::input type="search" wire:model.live.debounce.300ms="votingMemberSearch" placeholder="Stimmberechtigte suchen…" />
                </x-filament::input.wrapper>

                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($attendance as $row)
                        <li wire:key="votingMember-{{ $row['votingMember']->id }}" class="flex items-center gap-3 py-2">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ $row['votingMember']->name }}</div>
                                <div class="text-xs text-gray-500">
                                    @if ($row['votes'] !== null)
                                        {{ $row['votes'] }} {{ $row['votes'] === 1 ? 'Stimme' : 'Stimmen' }}@if ($row['member_count'] !== null) ({{ $row['member_count'] }} Mitglieder)@endif · seit {{ $row['checked_in_at']?->format('H:i') }}
                                    @else
                                        {{ $row['votingMember']->isBoardMember() ? 'Präsidium' : $row['votingMember']->member_count.' Mitglieder' }} → {{ $row['votingMember']->votes }} {{ $row['votingMember']->votes === 1 ? 'Stimme' : 'Stimmen' }}
                                    @endif
                                </div>
                            </div>
                            @if ($canConduct)
                                {{ ($this->correctVotesAction)(['votingMember' => $row['votingMember']->id]) }}
                                @if ($row['votes'] !== null)
                                    <x-filament::button size="xs" color="success" icon="heroicon-m-check" wire:click="checkOut({{ $row['votingMember']->id }})" wire:confirm="{{ $row['votingMember']->name }} auschecken?">Anwesend</x-filament::button>
                                @else
                                    <x-filament::button size="xs" color="gray" wire:click="checkIn({{ $row['votingMember']->id }})">Einchecken</x-filament::button>
                                @endif
                            @elseif ($row['votes'] !== null)
                                <x-filament::badge color="success">Anwesend</x-filament::badge>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">Keine Stimmberechtigten gefunden.</li>
                    @endforelse
                </ul>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
