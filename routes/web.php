<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomePinsController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LandingInstallController;
use App\Http\Controllers\OverviewLayoutsController;
use App\Http\Controllers\RepositoryCardViewController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\RepositoryExcludedWorkflowsController;
use App\Http\Controllers\RepositoryLookbackController;
use App\Http\Controllers\RepositoryMetricController;
use App\Http\Controllers\RepositorySuggestionController;
use App\Http\Controllers\RepositorySyncController;
use App\Http\Controllers\RepositoryViewController;
use App\Http\Controllers\RepositoryWallController;
use App\Http\Controllers\Settings\BenchmarkController;
use App\Http\Controllers\Settings\LogController;
use App\Http\Controllers\Settings\ReportDefaultsController;
use App\Http\Controllers\Settings\SyncController;
use App\Http\Controllers\Settings\TeamController as TeamSettingsController;
use App\Http\Controllers\Settings\TeamMembershipController;
use App\Http\Controllers\Settings\TimelineEventController;
use App\Http\Controllers\TeamCardViewController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WallController;
use App\Http\Middleware\RequireLogin;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/', LandingController::class)->name('landing');
Route::get('/install', LandingInstallController::class)->name('landing.install');

Route::middleware(RequireLogin::class)->group(function () {
    Route::get('/dashboard', HomeController::class)->name('home');
    Route::put('/home/pins', [HomePinsController::class, 'update'])->name('home.pins');
    Route::put('/overview-layouts', [OverviewLayoutsController::class, 'update'])->name('overview-layouts.update');
    Route::get('/wall', WallController::class)->name('wall');
    Route::get('/repos', [RepositoryController::class, 'index'])->name('repositories.index');
    Route::post('/repos', [RepositoryController::class, 'store'])->name('repositories.store');
    Route::get('/repos/suggestions', [RepositorySuggestionController::class, 'index'])->middleware('throttle:120,1')->name('repositories.suggestions');
    Route::get('/repos/{owner}/{name}', [RepositoryController::class, 'show'])->name('repositories.show');
    Route::get('/repos/{owner}/{name}/wall', RepositoryWallController::class)->name('repositories.wall');
    Route::get('/repos/{owner}/{name}/metrics/{metric}', RepositoryMetricController::class)->name('repositories.metric');
    Route::delete('/repos/{repository}', [RepositoryController::class, 'destroy'])->name('repositories.destroy');
    Route::put('/repos/{repository}/lookback', [RepositoryLookbackController::class, 'update'])->name('repositories.lookback');
    Route::put('/repos/{repository}/excluded-workflows', [RepositoryExcludedWorkflowsController::class, 'update'])->name('repositories.excluded-workflows');
    Route::put('/repos/{repository}/view', [RepositoryViewController::class, 'update'])->name('repositories.view');
    Route::put('/repos/{repository}/card-view', [RepositoryCardViewController::class, 'update'])->name('repositories.card-view');
    Route::post('/repos/{repository}/sync', [RepositorySyncController::class, 'store'])->name('repositories.sync');
    Route::get('/settings', fn () => to_route('settings.syncs.index'))->name('settings');
    Route::get('/settings/syncs', [SyncController::class, 'index'])->name('settings.syncs.index');
    Route::post('/settings/syncs', [SyncController::class, 'store'])->name('settings.syncs.store');
    Route::get('/settings/log', [LogController::class, 'index'])->name('settings.log.index');
    Route::get('/settings/defaults', [ReportDefaultsController::class, 'edit'])->name('settings.defaults.edit');
    Route::put('/settings/defaults', [ReportDefaultsController::class, 'update'])->name('settings.defaults.update');
    Route::get('/settings/benchmarks', [BenchmarkController::class, 'edit'])->name('settings.benchmarks.edit');
    Route::put('/settings/benchmarks', [BenchmarkController::class, 'update'])->name('settings.benchmarks.update');
    Route::get('/settings/teams', [TeamSettingsController::class, 'index'])->name('settings.teams.index');
    Route::post('/settings/teams', [TeamSettingsController::class, 'store'])->name('settings.teams.store');
    Route::put('/settings/teams/{team}', [TeamSettingsController::class, 'update'])->name('settings.teams.update');
    Route::delete('/settings/teams/{team}', [TeamSettingsController::class, 'destroy'])->name('settings.teams.destroy');
    Route::post('/settings/teams/{team}/memberships', [TeamMembershipController::class, 'store'])->name('settings.teams.memberships.store');
    Route::put('/settings/memberships/{membership}', [TeamMembershipController::class, 'update'])->name('settings.memberships.update');
    Route::delete('/settings/memberships/{membership}', [TeamMembershipController::class, 'destroy'])->name('settings.memberships.destroy');
    Route::get('/settings/events', [TimelineEventController::class, 'index'])->name('settings.events.index');
    Route::post('/settings/events', [TimelineEventController::class, 'store'])->name('settings.events.store');
    Route::put('/settings/events/{event}', [TimelineEventController::class, 'update'])->name('settings.events.update');
    Route::delete('/settings/events/{event}', [TimelineEventController::class, 'destroy'])->name('settings.events.destroy');
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::put('/teams/{team}/card-view', [TeamCardViewController::class, 'update'])->name('teams.card-view');
});
