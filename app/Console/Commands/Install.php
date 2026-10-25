<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('houston:install')]
#[Description('Decide whether Houston asks for a login, and create the login')]
class Install extends Command
{
    public function handle(): int
    {
        // Composer runs this during `composer setup` without a terminal (like in CI), where the
        // prompts would answer with their defaults and fail, so treat that as non-interactive too.
        $hasTerminal = (defined('STDIN') && stream_isatty(STDIN)) || $this->laravel->runningUnitTests();

        if (! $this->input->isInteractive() || ! $hasTerminal) {
            $this->components->warn('Skipped setting up a login. Run `php artisan houston:install` to set one up.');

            return self::SUCCESS;
        }

        $requireLogin = confirm(
            label: 'Should Houston ask for a login?',
            default: (bool) config('houston.auth'),
            hint: 'Say yes when it is reachable by people who shouldn\'t see it, like on a public server.',
        );

        if ($requireLogin) {
            $this->setUpLogin();
        }

        if (! $this->writeEnvironment($requireLogin)) {
            return self::FAILURE;
        }

        $this->components->info($requireLogin ? 'Houston now asks for a login: authorized personnel only.' : 'Houston is open to anyone who can reach it.');

        return self::SUCCESS;
    }

    /**
     * Houston has a single login, so a new one replaces whatever was there before.
     */
    private function setUpLogin(): void
    {
        $existing = User::query()->first();

        if ($existing !== null && ! confirm("Replace the login for {$existing->email}?", default: false)) {
            return;
        }

        $email = text(
            label: 'Email',
            default: $existing->email ?? '',
            required: true,
            validate: fn (string $value): ?string => Validator::make(['email' => $value], ['email' => ['email']])->errors()->first('email') ?: null,
        );

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => Validator::make(['password' => $value], ['password' => [Password::default()]])->errors()->first('password') ?: null,
        );

        password(
            label: 'Confirm the password',
            required: true,
            validate: fn (string $value): ?string => $value === $password ? null : 'The passwords don\'t match.',
        );

        DB::transaction(function () use ($email, $password): void {
            User::query()->delete();
            User::query()->create(['name' => 'Houston', 'email' => $email, 'password' => $password]);
        });
    }

    /**
     * An installed copy is for looking at your own repositories, so it also turns the website off
     * and / goes straight to the dashboard.
     */
    private function writeEnvironment(bool $requireLogin): bool
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path)) {
            $this->components->error("There is no {$path}. Set HOUSTON_AUTH=".($requireLogin ? 'true' : 'false').' and HOUSTON_LANDING=false in your environment instead.');

            return false;
        }

        Env::writeVariable('HOUSTON_AUTH', $requireLogin ? 'true' : 'false', $path, overwrite: true);
        Env::writeVariable('HOUSTON_LANDING', 'false', $path, overwrite: true);

        if ($this->laravel->configurationIsCached()) {
            $this->components->warn('The configuration is cached. Run `php artisan config:cache` to pick up the change.');
        }

        return true;
    }
}
