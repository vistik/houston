<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache of GitHub Actions workflow runs. Safe to truncate at any time.
     */
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id');
            $table->unsignedBigInteger('workflow_id');
            $table->string('name');
            $table->string('event');
            $table->string('status');
            $table->string('conclusion')->nullable();
            $table->string('head_branch')->nullable();
            $table->string('head_sha');
            $table->unsignedInteger('run_attempt')->default(1);
            $table->unsignedInteger('pull_request_number')->nullable();
            $table->string('url');
            $table->timestamp('run_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('queue_seconds')->nullable();
            $table->timestamp('created_at');

            $table->unique(['repository_id', 'github_id']);
            $table->index(['repository_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
