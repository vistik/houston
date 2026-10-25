<?php

namespace App\Providers;

use App\GitHub\GitHubClient;
use App\Metrics\Benchmarks;
use App\Settings\ReportDefaults;
use App\Support\Demo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GitHubClient::class);
        $this->app->scoped(Benchmarks::class);
        // Reports ask for the defaults many times per request, so read them once per request or job.
        $this->app->scoped(ReportDefaults::class, fn (): ReportDefaults => ReportDefaults::load());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDemo();

        DevCommands::artisan('schedule:work', 'scheduler');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // The demo reseeds its made-up data every night.
        DB::prohibitDestructiveCommands(
            app()->isProduction() && ! Demo::enabled(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Anyone may look around the demo, so it has no login, and its error pages never show the environment.
     */
    protected function configureDemo(): void
    {
        if (Demo::enabled()) {
            config(['houston.auth' => false, 'app.debug' => false]);
        }
    }
}
