<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Games\GameController;
use App\Http\Controllers\Games\GameEventController;
use App\Http\Controllers\Games\NarratorController;
use App\Http\Controllers\Games\VoteController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('games')->name('games.')->group(function () {
        Route::get('/', [GameController::class, 'index'])->name('index');
        Route::get('create', [GameController::class, 'create'])->name('create');
        Route::post('/', [GameController::class, 'store'])->name('store');
        Route::post('join', [GameController::class, 'join'])->name('join');
        Route::get('{game}', [GameController::class, 'show'])->name('show');
        Route::delete('{game}', [GameController::class, 'destroy'])->name('destroy');

        Route::post('{game}/start', [NarratorController::class, 'start'])->name('start');
        Route::post('{game}/advance-to-day', [NarratorController::class, 'advanceToDay'])->name('advance-to-day');
        Route::post('{game}/start-voting', [NarratorController::class, 'startVoting'])->name('start-voting');
        Route::post('{game}/resolve-votes', [NarratorController::class, 'resolveVotes'])->name('resolve-votes');
        Route::post('{game}/night-action', [NarratorController::class, 'nightAction'])->name('night-action');
        Route::post('{game}/skip-action', [NarratorController::class, 'skipAction'])->name('skip-action');
        Route::post('{game}/heartbeat', [NarratorController::class, 'heartbeat'])->name('heartbeat');
        Route::post('{game}/end', [NarratorController::class, 'endGame'])->name('end');

        Route::post('{game}/call-werewolves', [NarratorController::class, 'callWerewolves'])->name('call-werewolves');
        Route::post('{game}/call-seer', [NarratorController::class, 'callSeer'])->name('call-seer');
        Route::post('{game}/call-witch', [NarratorController::class, 'callWitch'])->name('call-witch');
        Route::post('{game}/conclude-night', [NarratorController::class, 'concludeNight'])->name('conclude-night');

        Route::post('{game}/vote', [VoteController::class, 'cast'])->name('vote');
        Route::get('{game}/stream', [GameEventController::class, 'stream'])->name('stream');
    });
});

require __DIR__.'/settings.php';
