<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userId = auth()->id();

        $activeGames = Game::where('status', 'playing')
            ->whereHas('players', fn($q) => $q->where('name', auth()->user()->name))
            ->withCount('players')
            ->latest()
            ->get();

        $recentGames = Game::whereIn('status', ['waiting', 'finished'])
            ->whereHas('players', fn($q) => $q->where('name', auth()->user()->name))
            ->withCount('players')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('dashboard', [
            'activeGames' => $activeGames,
            'recentGames' => $recentGames,
        ]);
    }
}
