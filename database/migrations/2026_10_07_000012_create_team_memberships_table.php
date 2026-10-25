<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who was on which team when. A GitHub login is on a team from the day it joined, or always when
     * that is not set, up to the day it left, or for good when that is not set.
     */
    public function up(): void
    {
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('login');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();
            $table->index('login');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
    }
};
