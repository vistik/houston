<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A log of what Houston does behind the scenes. It is kept apart from the GitHub cache, so flushing
     * the cache leaves the log alone.
     */
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level');
            $table->string('type');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->index();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
