<?php

namespace App\Providers;

use App\Services\VoteWeightCalculator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VoteWeightCalculator::class, fn () => VoteWeightCalculator::fromConfig());
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Dieselbe Mail dient als Einladung (neue Personen) und für „Passwort vergessen“.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Zugang zum DDHF MV Abstimmungssystem')
                ->greeting('Hallo!')
                ->line('Für dich wurde ein Zugang zum DDHF MV Abstimmungssystem eingerichtet bzw. ein neues Passwort angefordert.')
                ->action('Passwort setzen', $url)
                ->line("Der Link ist {$minutes} Minuten gültig. Danach kannst du über „Passwort vergessen?“ einen neuen anfordern.")
                ->line('Falls du damit nichts zu tun hast, kannst du diese E-Mail ignorieren.')
                ->salutation('Viele Grüße, der DDHF');
        });
    }
}
