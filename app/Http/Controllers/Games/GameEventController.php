<?php

namespace App\Http\Controllers\Games;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class GameEventController extends Controller
{
    public function stream(Request $request, int $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Allow any player in the game (no auth check for SSE)
        $response = response()->stream(function () use ($game) {
            $lastStatus = $game->status;
            $lastPhase = $game->current_phase;
            $lastActiveRole = $game->active_role;
            $lastPlayersCount = $game->players()->count();

            while (true) {
                $game->refresh();

                $currentStatus = $game->status;
                $currentPhase = $game->current_phase;
                $currentActiveRole = $game->active_role;
                $currentPlayersCount = $game->players()->count();

                $changed = 
                    $currentStatus !== $lastStatus ||
                    $currentPhase !== $lastPhase ||
                    $currentActiveRole !== $lastActiveRole ||
                    $currentPlayersCount !== $lastPlayersCount;

                if ($changed) {
                    $data = json_encode([
                        'status' => $currentStatus,
                        'phase' => $currentPhase,
                        'active_role' => $currentActiveRole,
                        'players_count' => $currentPlayersCount,
                        'round' => $game->round,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    
                    echo "data: {$data}\n\n";
                    @ob_flush();
                    flush();
                }

                $lastStatus = $currentStatus;
                $lastPhase = $currentPhase;
                $lastActiveRole = $currentActiveRole;
                $lastPlayersCount = $currentPlayersCount;

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);

        return $response;
    }
}