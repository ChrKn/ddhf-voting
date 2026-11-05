<?php

namespace App\Providers;

use App\Services\VoteWeightCalculator;
use Illuminate\Database\Eloquent\Model;
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
    }
}
