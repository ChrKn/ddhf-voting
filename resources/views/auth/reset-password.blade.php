<x-layouts::app title="Passwort setzen" heading="Passwort setzen">
    <div class="card mx-auto max-w-sm">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="label">E-Mail-Adresse</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input">
                @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="label">Neues Passwort (mind. 10 Zeichen)</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
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
