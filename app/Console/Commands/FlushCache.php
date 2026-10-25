<?php

namespace App\Console\Commands;

use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\Models\Deployment;
use App\Models\PullRequest;
use App\Models\PullRequestActivity;
use App\Models\PullRequestReview;
use App\Models\Repository;
use App\Models\RepositorySync;
use App\Models\SystemEvent;
use App\Models\WorkflowRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

#[Signature('houston:flush {--force : Skip the confirmation prompt}')]
#[Description('Wipe all cached GitHub data. Watched repositories are kept; the next sync rebuilds everything.')]
class FlushCache extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Wipe all cached GitHub data?')) {
            return self::SUCCESS;
        }

        // Wiping tables under a running sync would leave it writing a half-built cache.
        $locks = [];

        foreach (Repository::all() as $repository) {
            $lock = Cache::lock("houston:sync:{$repository->id}", 60);

            if (! $lock->get()) {
                collect($locks)->each->release();
                $this->components->error("{$repository->fullName()} is being synced right now. Try again when it finishes.");

                return self::FAILURE;
            }

            $locks[] = $lock;
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            PullRequestReview::query()->truncate();
            PullRequestActivity::query()->truncate();
            Deployment::query()->truncate();
            PullRequest::query()->truncate();
            WorkflowRun::query()->truncate();
            RepositorySync::query()->truncate();
        });

        collect($locks)->each->release();

        SystemEvent::record(SystemEventType::CacheFlushed, 'Flushed all cached GitHub data, the next syncs rebuild it', level: SystemEventLevel::Warning);

        $this->components->info('Cache flushed. Run `php artisan houston:sync` to rebuild it.');

        return self::SUCCESS;
    }
}
