<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The watched repositories. This is the only table that is a source of truth,
     * everything else Houston stores can be rebuilt from GitHub.
     */
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('owner');
            $table->string('name');
            $table->unsignedSmallInteger('lookback_days')->nullable();
            $table->json('excluded_workflow_ids')->nullable();
            $table->timestamps();

            $table->unique(['owner', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
