<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bookkeeping for the GitHub cache, and progress of the sync that is running so the UI can show
     * what is happening and estimate how long is left. Truncating this forces a full re-sync.
     */
    public function up(): void
    {
        Schema::create('repository_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('rate_limited_until')->nullable();
            $table->string('phase')->nullable();
            $table->timestamp('phase_started_at')->nullable();
            $table->boolean('backfill')->default(false);
            $table->timestamp('history_from')->nullable();
            $table->string('default_branch')->nullable();
            $table->string('description')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamp('repository_created_at')->nullable();
            $table->string('deploy_source')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('pull_requests_synced_at')->nullable();
            $table->timestamp('pull_requests_resume_started_at')->nullable();
            $table->timestamp('pull_requests_from')->nullable();
            $table->timestamp('pull_requests_reached_at')->nullable();
            $table->unsignedInteger('pull_requests_fetched')->default(0);
            $table->timestamp('workflow_runs_synced_at')->nullable();
            $table->timestamp('workflow_runs_from')->nullable();
            $table->timestamp('workflow_runs_reached_at')->nullable();
            $table->unsignedInteger('workflow_runs_fetched')->default(0);
            $table->timestamp('deployments_synced_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('last_duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_syncs');
    }
};
