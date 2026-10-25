<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Targets people set for a metric. Unlike the GitHub cache, these are settings and must be kept.
     */
    public function up(): void
    {
        Schema::create('benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('metric')->unique();
            $table->string('direction');
            $table->double('elite');
            $table->double('good');
            $table->double('medium');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmarks');
    }
};
