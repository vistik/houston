<?php

use App\Models\SystemEvent;
use Illuminate\Support\Facades\Schedule;

Schedule::command('houston:sync --queue')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [SystemEvent::class]])->daily();
