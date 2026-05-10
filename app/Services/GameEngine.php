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

            $duration = $game->mode === 'auto_narrator' ? self::PHASE_DURATIONS['night'] : null;

            $game->update([
                'status' => 'playing',
                'current_phase' => 'night',
                'phase_ends_at' => $duration ? now()->addSeconds($duration) : null,
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

    public const PHASE_DURATIONS = [
        'night' => 45,
        'day' => 120,
        'voting' => 45,
    ];

    public function changePhase(Game $game, string $phase, ?int $durationSeconds = null): void
    {
        $validPhases = ['night', 'day', 'voting'];
        if (!in_array($phase, $validPhases)) {
            throw new \InvalidArgumentException("Invalid phase: $phase");
        }

        $duration = $durationSeconds ?? (self::PHASE_DURATIONS[$phase] ?? null);
        $phaseEndsAt = $duration ? now()->addSeconds($duration) : null;

        $game->update([
            'current_phase' => $phase,
            'phase_ends_at' => $phaseEndsAt,
        ]);
        $this->logEvent($game, 'phase_change', [
            'phase' => $phase,
            'duration_seconds' => $duration,
        ]);
    }

    public function advanceToDay(Game $game): array
    {
        $results = $this->processNightActions($game);

        $this->changePhase($game, 'day');
        $this->logEvent($game, 'night_results', $results);

        $this->snapshot($game);

        return $results;
    }

    public function autoTick(Game $game): bool
    {
        if ($game->status !== 'playing' || $game->mode !== 'auto_narrator') {
            return false;
        }

        if ($game->current_phase === 'night' && $this->allNightActionsComplete($game)) {
            $this->autoAdvanceNight($game);
            return true;
        }

        if ($game->current_phase === 'voting' && $this->allVotesComplete($game, $game->round)) {
            $this->autoAdvanceVoting($game);
            return true;
        }

        if (!$game->phase_ends_at || now()->lessThan($game->phase_ends_at)) {
            return false;
        }

        return match ($game->current_phase) {
            'night' => $this->autoAdvanceNight($game),
            'day' => $this->autoAdvanceDay($game),
            'voting' => $this->autoAdvanceVoting($game),
            default => false,
        };
    }

    private function autoAdvanceNight(Game $game): bool
    {
        $this->advanceToDay($game);
        return true;
    }

    private function autoAdvanceDay(Game $game): bool
    {
        $this->changePhase($game, 'voting');
        $this->logEvent($game, 'day_results', ['message' => 'Discussion time ended. Starting vote.']);
        $this->snapshot($game);
        return true;
    }

    private function autoAdvanceVoting(Game $game): bool
    {
        $this->processVotes($game, $game->round);
        $winner = $this->checkWinCondition($game);

        if (!$winner) {
            $game->increment('round');
            $this->changePhase($game, 'night');
        }

        $this->snapshot($game);
        return true;
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

    public function skipNightAction(Game $game, GamePlayer $player): GameAction
    {
        return $this->recordAction($game, $player, 'skip', null, ['skipped' => true]);
    }

    public function allNightActionsComplete(Game $game): bool
    {
        $alivePlayers = $game->players()->where('is_alive', true)->with('role.actions')->get();

        $actors = $alivePlayers->filter(fn($p) => $p->role && $p->role->actions->where('phase', 'night')->isNotEmpty());

        if ($actors->isEmpty()) {
            return true;
        }

        $nightActions = GameAction::where('game_id', $game->id)
            ->where('phase', 'night')
            ->whereIn('player_id', $actors->pluck('id'))
            ->get();

        foreach ($actors as $actor) {
            $hasActed = $nightActions->where('player_id', $actor->id)->isNotEmpty();
            if (!$hasActed) {
                return false;
            }
        }

        return true;
    }

    public function allVotesComplete(Game $game, int $round): bool
    {
        $aliveCount = $game->players()->where('is_alive', true)->count();
        $voteCount = Vote::where('game_id', $game->id)->where('round', $round)->count();

        return $voteCount >= $aliveCount;
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
