<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache of pull requests fetched from GitHub. Safe to truncate at any time.
     */
    public function up(): void
    {
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->string('url');
            $table->string('state');
            $table->boolean('is_draft')->default(false);
            $table->string('author')->nullable();
            $table->string('author_avatar_url')->nullable();
            $table->boolean('author_is_bot')->default(false);
            $table->string('base_ref')->nullable();
            $table->string('head_ref')->nullable();
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('changed_files')->default(0);
            $table->unsignedInteger('commits_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('review_comments_count')->default(0);
            $table->string('checks_state')->nullable();
            $table->json('labels')->nullable();
            $table->timestamp('first_commit_at')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('ready_at')->nullable();
            $table->json('draft_intervals')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('first_review_at')->nullable();
            $table->timestamp('first_approval_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'number']);
            $table->index(['repository_id', 'merged_at']);
            $table->index(['repository_id', 'opened_at']);
            $table->index(['repository_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
