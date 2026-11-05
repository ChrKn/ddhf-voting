<x-layouts::app title="Passwort vergessen" heading="Passwort vergessen">
    <div class="card mx-auto max-w-sm">
        <p class="mb-4 text-sm text-neutral-600">
            Gib deine E-Mail-Adresse ein. Du erhältst einen Link, mit dem du ein neues Passwort setzen kannst.
        </p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="label">E-Mail-Adresse</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
                @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn w-full">Link anfordern</button>
            <p class="text-center text-sm">
                <a href="{{ route('login') }}" class="text-neutral-600 underline hover:text-ddhf">Zurück zum Login</a>
            </p>
        </form>
    </div>
</x-layouts::app>
