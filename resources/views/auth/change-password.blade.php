<x-layouts::app title="Eigenes Passwort festlegen" heading="Eigenes Passwort festlegen">
    <div class="card mx-auto max-w-sm">
        <p class="mb-4 text-sm">
            Sie haben sich mit einem vorläufigen Passwort angemeldet. Bitte legen Sie jetzt ein eigenes fest.
        </p>

        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf

            <div>
                <label for="password" class="label">Neues Passwort (mind. 10 Zeichen)</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="input">
                @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="label">Passwort wiederholen</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>

            <button type="submit" class="btn w-full">Passwort speichern</button>
        </form>
    </div>
</x-layouts::app>
