<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the repository is private on GitHub, or null until a sync has fetched it.
     */
    public function up(): void
    {
        Schema::table('repository_syncs', function (Blueprint $table) {
            $table->boolean('is_private')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('repository_syncs', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
