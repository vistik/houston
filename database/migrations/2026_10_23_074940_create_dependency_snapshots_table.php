<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far behind a repository's dependencies were, one row per day, so the drift can be followed
     * over time. The last check of a day overwrites the earlier ones.
     */
    public function up(): void
    {
        Schema::create('dependency_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('total');
            $table->unsignedInteger('outdated');
            $table->unsignedInteger('major');
            $table->timestamp('checked_at');

            $table->unique(['repository_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_snapshots');
    }
};
