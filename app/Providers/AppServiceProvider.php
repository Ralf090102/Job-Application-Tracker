<?php

namespace App\Providers;

use App\Contracts\JobPostingExtractor;
use App\Services\OllamaJobPostingExtractor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound to the interface, not the concrete class, so tests can
        // swap in a fake without touching the controller — see
        // tests/Feature/JobPostingExtractionTest.php.
        $this->app->bind(JobPostingExtractor::class, OllamaJobPostingExtractor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
