<?php

namespace App\Console\Commands;

use App\Enums\RepositorySyncStatus;
use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\GitHub\RepositorySynchronizer;
use App\GitHub\SyncInProgressException;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use App\Models\SystemEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\View\TaskResult;
use Throwable;

#[Signature('houston:sync {repository? : owner/name of a watched repository} {--queue : Dispatch to the queue instead of running inline}')]
#[Description('Refresh the GitHub cache for watched repositories')]
class SyncRepositories extends Command
{
    private const string RepositoryPattern = '~^(?<owner>[A-Za-z0-9_.-]+)/(?<name>[A-Za-z0-9_.-]+)$~';

    public function handle(RepositorySynchronizer $synchronizer): int
    {
        $fullName = $this->argument('repository');
        $coordinates = null;

        if ($fullName !== null) {
            if (! preg_match(self::RepositoryPattern, $fullName, $matches)) {
                $this->components->error("Invalid repository [{$fullName}]. Use the owner/name format, e.g. laravel/framework.");

                return self::FAILURE;
            }

            $coordinates = ['owner' => $matches['owner'], 'name' => $matches['name']];
        }

        $repositories = Repository::query()
            ->when($coordinates !== null, fn ($query) => $query->where($coordinates))
            ->get();

        if ($repositories->isEmpty()) {
            return $this->reportNoMatch($fullName);
        }

        $failed = false;

        foreach ($repositories as $repository) {
            if ($this->option('queue')) {
                SyncRepository::trigger($repository, $this->argument('repository') ? 'command line' : 'scheduled');
                $this->components->info("Queued {$repository->fullName()}");

                continue;
            }

            $error = null;

            $this->components->task($repository->fullName(), function () use ($synchronizer, $repository, &$error): int {
                try {
                    $synchronizer->sync($repository);

                    return TaskResult::Success->value;
                } catch (SyncInProgressException) {
                    SystemEvent::record(SystemEventType::SyncSkipped, "Skipped syncing {$repository->fullName()} from the command line, another sync is already running", $repository);

                    return TaskResult::Skipped->value;
                } catch (Throwable $exception) {
                    $state = $repository->syncState();

                    SystemEvent::record(SystemEventType::SyncFailed, "Sync of {$repository->fullName()} failed: {$exception->getMessage()}", $repository, [
                        'phase' => $state->phase,
                        'exception' => $exception::class,
                        'source' => 'command line',
                    ], SystemEventLevel::Error);

                    $state->update([
                        'status' => RepositorySyncStatus::Failed,
                        'error' => $exception->getMessage(),
                        'finished_at' => now(),
                    ]);
                    $error = $exception->getMessage();

                    return TaskResult::Failure->value;
                }
            });

            if ($error !== null) {
                $this->components->error($error);
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function reportNoMatch(?string $fullName): int
    {
        if ($fullName === null) {
            $this->components->warn('No matching watched repositories.');

            return self::FAILURE;
        }

        $this->components->error("{$fullName} is not a watched repository.");

        $watched = Repository::query()->orderBy('owner')->orderBy('name')->get()->map->fullName();

        if ($watched->isNotEmpty()) {
            $this->components->info('Watched repositories:');
            $this->components->bulletList($watched->all());
        }

        return self::FAILURE;
    }
}
