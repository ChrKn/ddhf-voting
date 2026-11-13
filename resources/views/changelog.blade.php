<x-layouts::app title="Changelog" heading="Changelog">
    <p class="mb-4 text-center text-sm text-neutral-600">
        Was sich von Version zu Version geändert hat. Nur auf Englisch.
    </p>

    <div class="card">
        {{-- Englisch, und für Screenreader als solches markiert. --}}
        <div class="changelog" lang="en">
            {!! $html !!}
        </div>
    </div>
</x-layouts::app>
