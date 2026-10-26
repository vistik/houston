<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache of the jobs of re-run GitHub Actions workflow runs, every attempt of each. Safe to truncate
     * at any time, together with workflow_runs.jobs_attempt.
     */
    public function up(): void
    {
        Schema::create('workflow_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id');
            $table->string('name');
            $table->unsignedInteger('run_attempt')->default(1);
            $table->string('conclusion')->nullable();
            $table->string('url');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('failed_step')->nullable();

            $table->unique(['workflow_run_id', 'github_id']);
        });

        // The attempt whose jobs are cached, so a run is only fetched again once it was re-run since.
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->unsignedInteger('jobs_attempt')->nullable()->after('run_attempt');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn('jobs_attempt');
        });

        Schema::dropIfExists('workflow_jobs');
    }
};
