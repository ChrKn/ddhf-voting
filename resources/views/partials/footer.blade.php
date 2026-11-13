<footer class="border-t border-neutral-300 py-4 text-center text-xs text-neutral-600">
    <address class="not-italic">
        Deutscher Dachverband für historisches Fechten e.V.<br>
        Fuhlsbüttler Straße 472<br>
        22309 Hamburg
    </address>
    <div class="mt-2">
        <a href="https://www.ddhf.de/impressum/" target="_blank" rel="noopener" class="underline">Impressum</a>
        –
        <a href="https://www.ddhf.de/datenschutz/" target="_blank" rel="noopener" class="underline">Datenschutz</a>
    </div>
    {{-- Aus CHANGELOG.md gelesen - siehe App\Support\Changelog. Weggelassen statt geraten, wenn
         die Datei kein Release nennt. --}}
    @php($version = \App\Support\Changelog::version())
    @if ($version)
        <div class="mt-2 tabular-nums">
            Version {{ $version }}
            · <a href="{{ route('changelog') }}" lang="en" class="underline">Changelog</a>
        </div>
    @endif
</footer>
