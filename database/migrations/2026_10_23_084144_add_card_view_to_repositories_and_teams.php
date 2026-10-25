<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->string('card_view')->nullable()->after('view');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('card_view')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn('card_view');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('card_view');
        });
    }
};
