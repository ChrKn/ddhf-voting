<x-layouts::app title="Anmelden" heading="Wahl-O-Mat Login">
    <div class="card mx-auto max-w-sm">
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="label">E-Mail-Adresse</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
                @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="label">Passwort</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
                @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-neutral-300">
                Angemeldet bleiben
            </label>

            <button type="submit" class="btn w-full">Anmelden</button>

            <p class="text-center text-sm">
                <a href="{{ route('password.request') }}" class="text-neutral-600 underline hover:text-ddhf">Passwort vergessen?</a>
            </p>
        </form>
    </div>
</x-layouts::app>
