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
            'potion' => 'nullable|string',
        ]);

        $player = GamePlayer::findOrFail($validated['player_id']);

        $metadata = [];
        if (!empty($validated['potion'])) {
            $metadata['potion'] = $validated['potion'];
        }

        $this->engine->recordAction(
            $game,
            $player,
            $validated['type'],
            $validated['target_id'],
            $metadata,
        );

        return redirect()->route('games.show', $game);
    }

    public function skipAction(Request $request, Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $validated = $request->validate([
            'player_id' => 'required|exists:game_players,id',
        ]);

        $player = GamePlayer::findOrFail($validated['player_id']);

        $this->engine->skipNightAction($game, $player);

        return redirect()->route('games.show', $game);
    }

    public function heartbeat(Game $game): \Illuminate\Http\JsonResponse
    {
        $ticked = $this->engine->autoTick($game);

        $game->refresh();
        $game->loadCount('players');

        return response()->json([
            'ticked' => $ticked,
            'phase' => $game->current_phase,
            'status' => $game->status,
            'phase_ends_at' => $game->phase_ends_at?->toIso8601String(),
            'active_role' => $game->active_role,
            'players_count' => $game->players_count,
        ]);
    }

    public function endGame(Request $request, Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $winner = $request->input('winner', null);

        $this->engine->endGame($game, $winner ?? 'village');

        return redirect()->route('games.show', $game);
    }

    public function callWerewolves(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $game->update([
            'active_role' => 'werewolf',
            'called_at' => now(),
        ]);

        $this->engine->logEvent($game, 'narrator_call', [
            'role' => 'werewolf',
            'message' => 'The narrator calls the werewolves...',
        ]);

        return redirect()->route('games.show', $game);
    }

    public function callSeer(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $game->update([
            'active_role' => 'seer',
            'called_at' => now(),
        ]);

        $this->engine->logEvent($game, 'narrator_call', [
            'role' => 'seer',
            'message' => 'The narrator calls the seer...',
        ]);

        return redirect()->route('games.show', $game);
    }

    public function callWitch(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $game->update([
            'active_role' => 'witch',
            'called_at' => now(),
        ]);

        $this->engine->logEvent($game, 'narrator_call', [
            'role' => 'witch',
            'message' => 'The narrator calls the witch...',
        ]);

        return redirect()->route('games.show', $game);
    }

    public function concludeNight(Game $game): RedirectResponse
    {
        $this->ensureGamePlaying($game);

        $game->update(['active_role' => null]);

        $results = $this->engine->advanceToDay($game);

        return redirect()->route('games.show', $game)->with('nightResults', $results);
    }

    private function ensureGamePlaying(Game $game): void
    {
        abort_if($game->status !== 'playing', 400, 'Game is not in progress.');
    }
}
