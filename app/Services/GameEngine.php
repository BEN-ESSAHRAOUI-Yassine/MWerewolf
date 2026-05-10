<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameAction;
use App\Models\GameEvent;
use App\Models\GameStateSnapshot;
use App\Models\Role;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameEngine
{
    public function startGame(Game $game, array $roleComposition): void
    {
        DB::transaction(function () use ($game, $roleComposition) {
            $this->assignRoles($game, $roleComposition);

            $game->update([
                'status' => 'playing',
                'current_phase' => 'night',
                'round' => 1,
            ]);

            $this->logEvent($game, 'game_start', [
                'mode' => $game->mode,
                'role_composition' => $roleComposition,
            ]);

            $this->snapshot($game);
        });
    }

    public function assignRoles(Game $game, array $roleComposition): void
    {
        $players = $game->players()->where('is_alive', true)->get()->shuffle();

        $roles = collect($roleComposition)->flatMap(function ($count, $roleSlug) {
            return array_fill(0, $count, $roleSlug);
        })->shuffle();

        if ($roles->count() !== $players->count()) {
            throw new \InvalidArgumentException(
                'Role count (' . $roles->count() . ') must match player count (' . $players->count() . ').'
            );
        }

        foreach ($players as $i => $player) {
            $role = Role::where('slug', $roles[$i])->firstOrFail();
            $player->update(['role_id' => $role->id]);
        }
    }

    public function changePhase(Game $game, string $phase): void
    {
        $validPhases = ['night', 'day', 'voting'];
        if (!in_array($phase, $validPhases)) {
            throw new \InvalidArgumentException("Invalid phase: $phase");
        }

        $game->update(['current_phase' => $phase, 'phase_ends_at' => null]);
        $this->logEvent($game, 'phase_change', ['phase' => $phase]);
    }

    public function advanceToDay(Game $game): array
    {
        $results = $this->processNightActions($game);

        $this->changePhase($game, 'day');
        $this->logEvent($game, 'night_results', $results);

        $this->snapshot($game);

        return $results;
    }

    public function processNightActions(Game $game): array
    {
        $results = [
            'killed' => null,
            'saved' => false,
            'inspected' => null,
        ];

        $wolfTarget = $this->getWolfTarget($game);
        if ($wolfTarget) {
            $results['killed'] = $wolfTarget->id;
        }

        $witchSaveUsed = $this->getWitchAction($game, 'save');
        if ($witchSaveUsed && $wolfTarget && $witchSaveUsed->target_player_id === $wolfTarget->id) {
            $results['saved'] = true;
            $results['killed'] = null;
        } elseif ($witchSaveUsed && $wolfTarget) {
            // Witch saved someone else — wolf kill still goes through
        }

        $witchKill = $this->getWitchAction($game, 'kill');
        if ($witchKill) {
            $results['witch_kill'] = $witchKill->target_player_id;
            GamePlayer::where('id', $witchKill->target_player_id)->update(['is_alive' => false]);
            $this->logEvent($game, 'death', [
                'player_id' => $witchKill->target_player_id,
                'cause' => 'witch_kill',
            ]);
        }

        if ($results['killed']) {
            GamePlayer::where('id', $results['killed'])->update(['is_alive' => false]);
            $this->logEvent($game, 'death', [
                'player_id' => $results['killed'],
                'cause' => 'werewolf',
            ]);
        }

        $seerTarget = $this->getSeerTarget($game);
        if ($seerTarget) {
            $targetRole = GamePlayer::with('role')->find($seerTarget->target_player_id);
            $results['inspected'] = [
                'player_id' => $seerTarget->target_player_id,
                'faction' => $targetRole?->role?->faction,
            ];
        }

        return $results;
    }

    public function processVotes(Game $game, int $round): ?int
    {
        $votes = Vote::where('game_id', $game->id)->where('round', $round)->get();

        if ($votes->isEmpty()) {
            $this->logEvent($game, 'vote_result', ['eliminated' => null, 'reason' => 'no_votes']);
            return null;
        }

        $tally = $votes->groupBy('target_id')->map->count();

        $maxVotes = $tally->max();
        $eliminatedId = null;

        if ($tally->filter(fn($count) => $count === $maxVotes)->count() === 1) {
            $eliminatedId = $tally->sortDesc()->keys()->first();
            GamePlayer::where('id', $eliminatedId)->update(['is_alive' => false]);
            $this->logEvent($game, 'death', [
                'player_id' => $eliminatedId,
                'cause' => 'vote',
            ]);
        }

        $this->logEvent($game, 'vote_result', [
            'eliminated' => $eliminatedId,
            'tally' => $tally->toArray(),
        ]);

        return $eliminatedId;
    }

    public function checkWinCondition(Game $game): ?string
    {
        $alivePlayers = $game->players()->where('is_alive', true)->with('role')->get();

        $wolfCount = $alivePlayers->filter(fn($p) => $p->role?->faction === 'werewolf')->count();
        $villageCount = $alivePlayers->filter(fn($p) => $p->role?->faction === 'village')->count();

        if ($wolfCount === 0) {
            $this->endGame($game, 'village');
            return 'village';
        }

        if ($wolfCount >= $villageCount) {
            $this->endGame($game, 'werewolf');
            return 'werewolf';
        }

        return null;
    }

    public function endGame(Game $game, string $winner): void
    {
        $game->update([
            'status' => 'finished',
            'current_phase' => null,
        ]);

        $this->logEvent($game, 'game_end', ['winner' => $winner]);
        $this->snapshot($game);
    }

    public function recordAction(Game $game, GamePlayer $player, string $type, ?int $targetPlayerId, array $metadata = []): GameAction
    {
        return GameAction::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'type' => $type,
            'target_player_id' => $targetPlayerId,
            'phase' => $game->current_phase,
            'metadata' => $metadata,
        ]);
    }

    public function castVote(Game $game, GamePlayer $voter, GamePlayer $target, int $round): Vote
    {
        Vote::where('game_id', $game->id)
            ->where('voter_id', $voter->id)
            ->where('round', $round)
            ->delete();

        return Vote::create([
            'game_id' => $game->id,
            'voter_id' => $voter->id,
            'target_id' => $target->id,
            'round' => $round,
        ]);
    }

    public function getPlayerRoleData(GamePlayer $player): ?array
    {
        if (!$player->role) {
            return null;
        }

        return [
            'id' => $player->role->id,
            'name' => $player->role->name,
            'slug' => $player->role->slug,
            'faction' => $player->role->faction,
            'description' => $player->role->description,
        ];
    }

    private function getWolfTarget(Game $game): ?GameAction
    {
        $wolves = $game->players()
            ->whereHas('role', fn($q) => $q->where('slug', 'werewolf'))
            ->where('is_alive', true)
            ->pluck('id');

        return GameAction::where('game_id', $game->id)
            ->where('phase', 'night')
            ->where('type', 'kill')
            ->whereIn('player_id', $wolves)
            ->latest()
            ->first();
    }

    private function getSeerTarget(Game $game): ?GameAction
    {
        $seer = $game->players()
            ->whereHas('role', fn($q) => $q->where('slug', 'seer'))
            ->where('is_alive', true)
            ->first();

        if (!$seer) return null;

        return GameAction::where('game_id', $game->id)
            ->where('player_id', $seer->id)
            ->where('phase', 'night')
            ->where('type', 'inspect')
            ->latest()
            ->first();
    }

    private function getWitchAction(Game $game, string $subtype): ?GameAction
    {
        $witch = $game->players()
            ->whereHas('role', fn($q) => $q->where('slug', 'witch'))
            ->where('is_alive', true)
            ->first();

        if (!$witch) return null;

        return GameAction::where('game_id', $game->id)
            ->where('player_id', $witch->id)
            ->where('phase', 'night')
            ->where('type', $subtype)
            ->latest()
            ->first();
    }

    public function logEvent(Game $game, string $type, array $payload = []): GameEvent
    {
        return GameEvent::create([
            'game_id' => $game->id,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function snapshot(Game $game): GameStateSnapshot
    {
        $game->load(['players.role', 'players.actions']);

        return GameStateSnapshot::create([
            'game_id' => $game->id,
            'state' => $game->toArray(),
        ]);
    }

    public function generateRoomCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        } while (Game::where('code', $code)->exists());

        return $code;
    }

    public function getAvailableRolesForComposition(int $playerCount): Collection
    {
        return Role::where('is_active', true)->orderBy('faction')->orderBy('night_order')->get();
    }
}
