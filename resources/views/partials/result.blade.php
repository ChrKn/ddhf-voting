{{-- Ergebnis je Position als Balken. Erwartet $positions aus PollResult::positions() --}}
<div class="mt-3 space-y-5">
    @foreach ($positions as $position)
        <div>
            @if ($showTitles ?? true)
                <h3 class="mb-2 font-semibold">{{ $position['title'] }}</h3>
            @endif
            <ul class="space-y-2">
                @foreach ($position['options'] as $option)
                    <li>
                        <div class="flex justify-between text-sm">
                            <span @class(['italic text-neutral-600' => $option['is_abstention'], 'font-medium' => ! $option['is_abstention']])>
                                {{ $option['label'] }}
                                @if ($option['club_hint'])
                                    <span class="text-neutral-500">({{ $option['club_hint'] }})</span>
                                @endif
                            </span>
                            <span class="tabular-nums">
                                {{ $option['votes'] }} {{ $option['votes'] === 1 ? 'Stimme' : 'Stimmen' }}
                                @unless ($option['is_abstention'])
                                    <span class="text-neutral-500">· {{ number_format($option['share'], 1, ',', '.') }} %</span>
                                @endunless
                            </span>
                        </div>
                        @unless ($option['is_abstention'])
                            <div class="mt-1 h-2 overflow-hidden rounded bg-neutral-100">
                                <div class="h-full rounded" style="width: {{ $option['share'] }}%; background-color: #cc1d1b"></div>
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-neutral-500">
                {{ $position['total'] }} abgegebene Stimmen, davon {{ $position['valid'] }} ohne Enthaltung. Prozent bezogen auf Stimmen ohne Enthaltung.
            </p>
        </div>
    @endforeach
</div>
