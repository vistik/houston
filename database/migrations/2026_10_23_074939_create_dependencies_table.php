<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The direct dependencies a repository declares on its default branch, with the version its lock
     * file installs and the latest release on the package registry. Safe to truncate at any time.
     */
    public function up(): void
    {
        Schema::create('dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('ecosystem');
            $table->string('name');
            $table->string('constraint')->nullable();
            $table->string('version')->nullable();
            $table->string('latest_version')->nullable();
            $table->boolean('is_dev')->default(false);
            $table->string('drift');

            $table->unique(['repository_id', 'ecosystem', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencies');
    }
};
