<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Services\GameEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function __construct(
        private readonly GameEngine $engine
    ) {}

    public function index(): Response
    {
        $games = Game::withCount('players')
            ->where('created_by', auth()->id())
            ->latest()
            ->get();

        return Inertia::render('games/index', [
            'games' => $games,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('games/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => 'required|in:human_narrator,auto_narrator',
        ]);

        $game = Game::create([
            'code' => $this->engine->generateRoomCode(),
            'status' => 'waiting',
            'mode' => $validated['mode'],
            'created_by' => auth()->id(),
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'name' => auth()->user()->name,
            'is_host' => true,
            'is_alive' => true,
        ]);

        return redirect()->route('games.show', $game);
    }

    public function show(Game $game): Response
    {
        if ($game->status === 'playing' && $game->mode === 'auto_narrator') {
            $this->engine->autoTick($game);
        }

        $game->load(['players.role', 'players.actions', 'votes', 'events', 'actions']);

        $game->loadCount('players');

        $isHost = $game->players()
            ->where('is_host', true)
            ->where('name', auth()->user()->name)
            ->exists();

        $myPlayer = $game->players()
            ->where('name', auth()->user()->name)
            ->first();

        $roles = [];
        if ($game->status === 'playing' && $myPlayer?->role) {
            $roles = $this->engine->getPlayerRoleData($myPlayer);
        }

        return Inertia::render('games/show', [
            'game' => $game,
            'isHost' => $isHost,
            'myPlayer' => $myPlayer,
            'myRole' => $roles,
            'availableRoles' => $game->status === 'waiting' && $isHost
                ? $this->engine->getAvailableRolesForComposition($game->players_count)
                : [],
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6|exists:games,code',
        ]);

        $game = Game::where('code', $validated['code'])->firstOrFail();

        if ($game->status !== 'waiting') {
            return back()->withErrors(['code' => 'This game has already started.']);
        }

        $existingPlayer = $game->players()
            ->where('name', auth()->user()->name)
            ->first();

        if ($existingPlayer) {
            return redirect()->route('games.show', $game);
        }

        GamePlayer::create([
            'game_id' => $game->id,
            'name' => auth()->user()->name,
            'is_host' => false,
            'is_alive' => true,
            'order_index' => $game->players()->count(),
        ]);

        return redirect()->route('games.show', $game);
    }

    public function destroy(Game $game): RedirectResponse
    {
        $this->authorizeGameAccess($game);

        $game->delete();
        return redirect()->route('games.index');
    }

    private function authorizeGameAccess(Game $game): void
    {
        $isCreator = $game->created_by === auth()->id();
        $isHost = $game->players()
            ->where('is_host', true)
            ->where('name', auth()->user()->name)
            ->exists();

        abort_unless($isCreator || $isHost, 403);
    }
}
