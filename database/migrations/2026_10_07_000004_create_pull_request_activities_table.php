<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache of pull request timeline activity (commits, force pushes, comments) used to work
     * out whose turn it was at any moment. Safe to truncate at any time.
     */
    public function up(): void
    {
        Schema::create('pull_request_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('actor')->nullable();
            $table->boolean('actor_is_bot')->default(false);
            $table->timestamp('occurred_at');

            $table->index(['pull_request_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_activities');
    }
};
