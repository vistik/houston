<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache of pull request reviews fetched from GitHub. Safe to truncate at any time.
     */
    public function up(): void
    {
        Schema::create('pull_request_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->string('reviewer');
            $table->boolean('reviewer_is_bot')->default(false);
            $table->string('state');
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('submitted_at');

            $table->index(['pull_request_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_reviews');
    }
};
