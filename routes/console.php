<?php

use App\Models\SystemEvent;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Schedule;

Schedule::command('houston:sync --queue')->everyFifteenMinutes()->withoutOverlapping()->skip(Demo::enabled(...));
Schedule::command('model:prune', ['--model' => [SystemEvent::class]])->daily();

// The demo's made-up history ends the day it is seeded, so reseed it every night to keep recent periods full.
Schedule::command('migrate:fresh', ['--seeder' => DemoSeeder::class, '--force' => true])->daily()->when(Demo::enabled(...));
