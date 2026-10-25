<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache of production deployments, taken from GitHub Deployments or releases.
     * Safe to truncate at any time.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('environment')->nullable();
            $table->string('ref')->nullable();
            $table->string('sha')->nullable();
            $table->string('state');
            $table->string('url')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('deployed_at');

            $table->unique(['repository_id', 'source', 'external_id']);
            $table->index(['repository_id', 'deployed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
