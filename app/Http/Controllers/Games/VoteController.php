<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Services\GameEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function __construct(
        private readonly GameEngine $engine
    ) {}

    public function cast(Request $request, Game $game): RedirectResponse
    {
        if ($game->current_phase !== 'voting') {
            return back()->withErrors(['vote' => 'Voting is not active.']);
        }

        $validated = $request->validate([
            'target_id' => 'required|exists:game_players,id',
        ]);

        $voter = $game->players()
            ->where('name', auth()->user()->name)
            ->firstOrFail();

        if (!$voter->is_alive) {
            return back()->withErrors(['vote' => 'Dead players cannot vote.']);
        }

        $target = GamePlayer::findOrFail($validated['target_id']);

        if (!$target->is_alive) {
            return back()->withErrors(['vote' => 'Cannot vote for a dead player.']);
        }

        $this->engine->castVote($game, $voter, $target, $game->round);

        return redirect()->route('games.show', $game);
    }
}
