<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Services\GameEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NarratorController extends Controller
{
    public function __construct(
        private readonly GameEngine $engine
    ) {}

    public function start(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'integer|min:0',
        ]);

        $roleComposition = collect($validated['roles'])->filter(fn($count) => $count > 0)->toArray();

        $totalRoles = array_sum($roleComposition);
        $playerCount = $game->players()->where('is_alive', true)->count();

        if ($totalRoles !== $playerCount) {
            return back()->withErrors(['roles' => "Role count ($totalRoles) must match player count ($playerCount)."]);
        }

        $this->engine->startGame($game, $roleComposition);

        return redirect()->route('games.show', $game);
    }

    public function advanceToDay(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $results = $this->engine->advanceToDay($game);

        return redirect()->route('games.show', $game)->with('nightResults', $results);
    }

    public function startVoting(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $this->engine->changePhase($game, 'voting');

        return redirect()->route('games.show', $game);
    }

    public function resolveVotes(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $eliminated = $this->engine->processVotes($game, $game->round);
        $winner = $this->engine->checkWinCondition($game);

        if ($winner) {
            return redirect()->route('games.show', $game);
        }

        $game->increment('round');
        $this->engine->changePhase($game, 'night');

        return redirect()->route('games.show', $game);
    }

    public function nightAction(Request $request, Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $validated = $request->validate([
            'player_id' => 'required|exists:game_players,id',
            'type' => 'required|string',
            'target_id' => 'nullable|exists:game_players,id',
        ]);

        $player = GamePlayer::findOrFail($validated['player_id']);

        $this->engine->recordAction(
            $game,
            $player,
            $validated['type'],
            $validated['target_id'],
        );

        return redirect()->route('games.show', $game);
    }

    public function endGame(Request $request, Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $winner = $request->input('winner', null);

        $this->engine->endGame($game, $winner ?? 'village');

        return redirect()->route('games.show', $game);
    }

    private function ensureGamePlaying(Game $game): void
    {
        abort_if($game->status !== 'playing', 400, 'Game is not in progress.');
    }
}
