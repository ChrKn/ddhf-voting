<x-filament-panels::page>
    <div class="space-y-6">
        @if ($summary)
            <x-filament::section>
                <x-slot name="heading">Ergebnis des letzten Imports</x-slot>
                <ul class="space-y-1 text-sm">
                    <li><strong>{{ $summary['created'] }}</strong> neu angelegt</li>
                    <li><strong>{{ $summary['updated'] }}</strong> aktualisiert, davon <strong>{{ $summary['renamed'] }}</strong> umbenannt</li>
                    <li><strong>{{ $summary['unchanged'] }}</strong> unverändert</li>
                    <li><strong>{{ $summary['skipped'] }}</strong> übersprungen</li>
                </ul>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Laufende Versammlungen behalten die Stimmen aus dem Check-in.</p>
            </x-filament::section>
        @endif

        {{-- 1. Datei --}}
        <x-filament::section>
            <x-slot name="heading">1. Datei auswählen</x-slot>
            <x-slot name="description">CSV (Semikolon, Komma oder Tab) oder Excel (.xlsx). Benötigt werden eine Spalte mit dem Namen und eine mit der Anzahl Mitglieder.</x-slot>

            <input type="file" wire:model="upload" accept=".csv,.txt,.xlsx"
                class="block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500">
            <div wire:loading wire:target="upload" class="mt-2 text-sm text-gray-500">Datei wird gelesen…</div>
            @error('upload') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
        </x-filament::section>

        @if ($rows)
            {{-- 2. Spalten --}}
            <x-filament::section>
                <x-slot name="heading">2. Spalten</x-slot>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Name</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="nameColumn">
                                @foreach ($columns as $i => $label)
                                    <option value="{{ $i }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Anzahl Mitglieder</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="countColumn">
                                @foreach ($columns as $i => $label)
                                    <option value="{{ $i }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label class="flex items-end gap-2 pb-2 text-sm">
                        <x-filament::input.checkbox wire:model.live="hasHeader" />
                        <span>Erste Zeile ist eine Überschrift</span>
                    </label>
                </div>
            </x-filament::section>

            {{-- 3. Zuordnung --}}
            <x-filament::section>
                <x-slot name="heading">3. Zuordnung prüfen</x-slot>
                <x-slot name="description">
                    Gleiche Namen werden automatisch zugeordnet. Wurde ein Mitglied umbenannt, hier das vorhandene Mitglied auswählen – der Name wird dann übernommen.
                </x-slot>

                <div class="mb-4 flex flex-wrap gap-2 text-sm">
                    <x-filament::badge color="success">{{ $stats['new'] }} neu</x-filament::badge>
                    <x-filament::badge color="warning">{{ $stats['update'] }} Änderungen</x-filament::badge>
                    <x-filament::badge color="gray">{{ $stats['unchanged'] }} unverändert</x-filament::badge>
                    <x-filament::badge color="danger">{{ $stats['skip'] }} übersprungen</x-filament::badge>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Zeile</th>
                                <th class="py-2 pr-3">Aus der Datei</th>
                                <th class="py-2 pr-3">Zuordnung</th>
                                <th class="py-2">Änderung</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                @php
                                    $club = is_numeric($row['target']) ? $clubs->get((int) $row['target']) : null;
                                @endphp
                                <tr wire:key="row-{{ $row['line'] }}" @class([
                                    'border-b border-gray-100 align-top dark:border-white/5',
                                    'opacity-60' => $row['target'] === 'skip',
                                ])>
                                    <td class="py-2 pr-3 tabular-nums text-gray-500">{{ $row['line'] }}</td>
                                    <td class="py-2 pr-3">
                                        <div class="font-medium">{{ $row['name'] ?: '–' }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['count'] ?? '–' }} Mitglieder</div>
                                        @if ($row['error'])
                                            <div class="text-xs text-danger-600">{{ $row['error'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3">
                                        @if ($row['error'])
                                            <span class="text-gray-500">wird übersprungen</span>
                                        @else
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select wire:model.live="rows.{{ $i }}.target">
                                                    <option value="new">➕ Neu anlegen</option>
                                                    <option value="skip">Überspringen</option>
                                                    <optgroup label="Vorhandenes Mitglied">
                                                        @foreach ($clubs as $c)
                                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                            @if ($row['target'] === 'new' && $row['similar'])
                                                <div class="mt-1 text-xs text-warning-600">Umbenannt? Ähnlich: „{{ $row['similar'] }}“</div>
                                            @endif
                                            @if ($club && $club->name !== $row['name'])
                                                <label class="mt-1 flex items-center gap-2 text-xs">
                                                    <x-filament::input.checkbox wire:model.live="rows.{{ $i }}.rename" />
                                                    <span>Namen ändern: „{{ $club->name }}“ → „{{ $row['name'] }}“</span>
                                                </label>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="py-2 text-xs">
                                        @if ($row['error'] || $row['target'] === 'skip')
                                            –
                                        @elseif ($row['target'] === 'new')
                                            neu · {{ $calculator->votesFor($row['count']) }} Stimme(n)
                                        @elseif ($club)
                                            @if ($club->member_count === $row['count'])
                                                <span class="text-gray-500">{{ $club->member_count }} Mitglieder, unverändert</span>
                                            @else
                                                {{ $club->member_count }} → <strong>{{ $row['count'] }}</strong> Mitglieder<br>
                                                {{ $calculator->votesFor($club->member_count) }} → <strong>{{ $calculator->votesFor($row['count']) }}</strong> Stimme(n)
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($missing->isNotEmpty())
                    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                        <div class="font-medium">Nicht in der Datei ({{ $missing->count() }}):</div>
                        <div class="mt-1 text-gray-600 dark:text-gray-400">{{ $missing->pluck('name')->implode(', ') }}</div>
                        <div class="mt-1 text-xs text-gray-500">Diese Mitglieder bleiben unverändert. Ausgetretene bitte von Hand auf inaktiv setzen.</div>
                    </div>
                @endif

                @if ($problems)
                    <div class="mt-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400" role="alert">
                        <div class="font-medium">Import nicht möglich:</div>
                        <div class="mt-1 whitespace-pre-line">{{ $problems }}</div>
                    </div>
                @endif

                <div class="mt-6 flex gap-3">
                    <x-filament::button wire:click="import" wire:confirm="Import jetzt ausführen?">Import ausführen</x-filament::button>
                    <x-filament::button color="gray" wire:click="resetImport">Abbrechen</x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
