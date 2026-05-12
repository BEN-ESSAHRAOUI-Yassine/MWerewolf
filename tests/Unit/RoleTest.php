<?php

use App\Models\Game;
use App\Models\GameAction;
use App\Models\GamePlayer;
use App\Models\Role;
use App\Models\Vote;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->engine = new GameEngine();

    // Create roles directly
    $this->werewolfRole = Role::updateOrCreate(
        ['slug' => 'werewolf'],
        ['name' => 'Werewolf', 'faction' => 'werewolf', 'description' => 'Test', 'night_order' => 1, 'is_active' => true]
    );
    $this->seerRole = Role::updateOrCreate(
        ['slug' => 'seer'],
        ['name' => 'Seer', 'faction' => 'village', 'description' => 'Test', 'night_order' => 2, 'is_active' => true]
    );
    $this->witchRole = Role::updateOrCreate(
        ['slug' => 'witch'],
        ['name' => 'Witch', 'faction' => 'village', 'description' => 'Test', 'night_order' => 3, 'max_uses' => ['save' => 1, 'kill' => 1], 'is_active' => true]
    );
    $this->villagerRole = Role::updateOrCreate(
        ['slug' => 'villager'],
        ['name' => 'Villager', 'faction' => 'village', 'description' => 'Test', 'night_order' => null, 'is_active' => true]
    );
});

describe('Role Model', function () {
    it('werewolf role exists with correct attributes', function () {
        expect($this->werewolfRole->name)->toBe('Werewolf');
        expect($this->werewolfRole->slug)->toBe('werewolf');
        expect($this->werewolfRole->faction)->toBe('werewolf');
        expect($this->werewolfRole->night_order)->toBe(1);
    });

    it('seer role exists with correct attributes', function () {
        expect($this->seerRole->name)->toBe('Seer');
        expect($this->seerRole->slug)->toBe('seer');
        expect($this->seerRole->faction)->toBe('village');
        expect($this->seerRole->night_order)->toBe(2);
    });

    it('witch role exists with correct attributes', function () {
        expect($this->witchRole->name)->toBe('Witch');
        expect($this->witchRole->slug)->toBe('witch');
        expect($this->witchRole->faction)->toBe('village');
        expect($this->witchRole->night_order)->toBe(3);
    });

    it('villager role exists with correct attributes', function () {
        expect($this->villagerRole->name)->toBe('Villager');
        expect($this->villagerRole->slug)->toBe('villager');
        expect($this->villagerRole->faction)->toBe('village');
    });
});

describe('Werewolf Role Functions', function () {
    it('werewolf can kill a villager during night', function () {
        $game = Game::create(['code' => 'TEST001', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $werewolf = GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'order_index' => 1, 'is_alive' => true, 'is_host' => false]);
        $villager = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 2, 'is_alive' => true, 'is_host' => false]);

        $action = $this->engine->recordAction($game, $werewolf, 'kill', $villager->id);

        expect($action->type)->toBe('kill');
        expect($action->target_player_id)->toBe($villager->id);
        expect($action->phase)->toBe('night');
    });

    it('werewolf can skip their action', function () {
        $game = Game::create(['code' => 'TEST002', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $werewolf = GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'order_index' => 1, 'is_alive' => true, 'is_host' => false]);

        $action = $this->engine->skipNightAction($game, $werewolf);

        expect($action->type)->toBe('skip');
        expect($action->player_id)->toBe($werewolf->id);
        expect($action->metadata['skipped'])->toBeTrue();
    });
});

describe('Seer Role Functions', function () {
    it('seer can inspect a player during night', function () {
        $game = Game::create(['code' => 'TEST005', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $seer = GamePlayer::create(['game_id' => $game->id, 'name' => 'Seer1', 'role_id' => $this->seerRole->id, 'order_index' => 1, 'is_alive' => true]);
        $werewolf = GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'order_index' => 2, 'is_alive' => true]);

        $action = $this->engine->recordAction($game, $seer, 'inspect', $werewolf->id);

        expect($action->type)->toBe('inspect');
        expect($action->target_player_id)->toBe($werewolf->id);
    });

    it('seer can skip inspection', function () {
        $game = Game::create(['code' => 'TEST006', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $seer = GamePlayer::create(['game_id' => $game->id, 'name' => 'Seer1', 'role_id' => $this->seerRole->id, 'order_index' => 1, 'is_alive' => true]);

        $action = $this->engine->skipNightAction($game, $seer);

        expect($action->type)->toBe('skip');
    });

    it('seer inspection reveals werewolf faction', function () {
        $game = Game::create(['code' => 'TEST007', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $seer = GamePlayer::create(['game_id' => $game->id, 'name' => 'Seer1', 'role_id' => $this->seerRole->id, 'order_index' => 1, 'is_alive' => true]);
        $werewolf = GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'order_index' => 2, 'is_alive' => true]);

        $this->engine->recordAction($game, $seer, 'inspect', $werewolf->id);

        $results = $this->engine->processNightActions($game);

        expect($results['inspected']['player_id'])->toBe($werewolf->id);
        expect($results['inspected']['faction'])->toBe('werewolf');
    });

    it('seer inspection reveals village faction', function () {
        $game = Game::create(['code' => 'TEST008', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $seer = GamePlayer::create(['game_id' => $game->id, 'name' => 'Seer1', 'role_id' => $this->seerRole->id, 'order_index' => 1, 'is_alive' => true]);
        $villager = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 2, 'is_alive' => true]);

        $this->engine->recordAction($game, $seer, 'inspect', $villager->id);

        $results = $this->engine->processNightActions($game);

        expect($results['inspected']['player_id'])->toBe($villager->id);
        expect($results['inspected']['faction'])->toBe('village');
    });
});

describe('Witch Role Functions', function () {
    it('witch can use save potion', function () {
        $game = Game::create(['code' => 'TEST009', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $witch = GamePlayer::create(['game_id' => $game->id, 'name' => 'Witch1', 'role_id' => $this->witchRole->id, 'order_index' => 1, 'is_alive' => true]);
        $villager = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 2, 'is_alive' => true]);

        $action = $this->engine->recordAction($game, $witch, 'save', $villager->id);

        expect($action->type)->toBe('save');
        expect($action->target_player_id)->toBe($villager->id);
    });

    it('witch can use kill potion', function () {
        $game = Game::create(['code' => 'TEST010', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $witch = GamePlayer::create(['game_id' => $game->id, 'name' => 'Witch1', 'role_id' => $this->witchRole->id, 'order_index' => 1, 'is_alive' => true]);
        $werewolf = GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'order_index' => 2, 'is_alive' => true]);

        $action = $this->engine->recordAction($game, $witch, 'kill', $werewolf->id);

        expect($action->type)->toBe('kill');
        expect($action->target_player_id)->toBe($werewolf->id);
    });

    it('witch can skip save action', function () {
        $game = Game::create(['code' => 'TEST013', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $witch = GamePlayer::create(['game_id' => $game->id, 'name' => 'Witch1', 'role_id' => $this->witchRole->id, 'order_index' => 1, 'is_alive' => true]);

        $action = $this->engine->recordAction($game, $witch, 'skip', null, ['potion' => 'save']);

        expect($action->type)->toBe('skip');
        expect($action->metadata['potion'])->toBe('save');
    });

    it('witch can skip kill action', function () {
        $game = Game::create(['code' => 'TEST014', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $witch = GamePlayer::create(['game_id' => $game->id, 'name' => 'Witch1', 'role_id' => $this->witchRole->id, 'order_index' => 1, 'is_alive' => true]);

        $action = $this->engine->recordAction($game, $witch, 'skip', null, ['potion' => 'kill']);

        expect($action->type)->toBe('skip');
        expect($action->metadata['potion'])->toBe('kill');
    });
});

describe('Villager Role Functions', function () {
    it('villager has no night actions', function () {
        $game = Game::create(['code' => 'TEST015', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        $villager = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 1, 'is_alive' => true]);

        $nightActions = GameAction::where('player_id', $villager->id)->where('phase', 'night')->get();

        expect($nightActions)->toBeEmpty();
    });

    it('villager can vote during voting phase', function () {
        $game = Game::create(['code' => 'TEST016', 'status' => 'playing', 'current_phase' => 'voting', 'mode' => 'human_narrator', 'round' => 1]);
        $villager1 = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 1, 'is_alive' => true]);
        $villager2 = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager2', 'role_id' => $this->villagerRole->id, 'order_index' => 2, 'is_alive' => true]);

        $vote = $this->engine->castVote($game, $villager1, $villager2, 1);

        expect($vote->voter_id)->toBe($villager1->id);
        expect($vote->target_id)->toBe($villager2->id);
    });

    it('villager can abstain from voting', function () {
        $game = Game::create(['code' => 'TEST017', 'status' => 'playing', 'current_phase' => 'voting', 'mode' => 'human_narrator', 'round' => 1]);
        $villager = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 1, 'is_alive' => true]);

        $vote = $this->engine->castVote($game, $villager, $villager, 1);

        expect($vote->voter_id)->toBe($vote->target_id);
    });

    it('villager is eliminated by vote', function () {
        $game = Game::create(['code' => 'TEST018', 'status' => 'playing', 'current_phase' => 'voting', 'mode' => 'human_narrator', 'round' => 1]);
        $villager1 = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 1, 'is_alive' => true]);
        $villager2 = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager2', 'role_id' => $this->villagerRole->id, 'order_index' => 2, 'is_alive' => true]);
        $villager3 = GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager3', 'role_id' => $this->villagerRole->id, 'order_index' => 3, 'is_alive' => true]);

        $this->engine->castVote($game, $villager2, $villager1, 1);
        $this->engine->castVote($game, $villager3, $villager1, 1);

        $eliminated = $this->engine->processVotes($game, 1);

        expect($eliminated)->toBe($villager1->id);
    });
});

describe('Role Win Conditions', function () {
    it('village wins when all werewolves are dead', function () {
        $game = Game::create(['code' => 'TEST019', 'status' => 'playing', 'mode' => 'human_narrator', 'round' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'is_alive' => false, 'order_index' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 2]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager2', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 3]);

        $winner = $this->engine->checkWinCondition($game);

        expect($winner)->toBe('village');
    });

    it('werewolves win when they equal village count', function () {
        $game = Game::create(['code' => 'TEST020', 'status' => 'playing', 'mode' => 'human_narrator', 'round' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'is_alive' => true, 'order_index' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 2]);

        $winner = $this->engine->checkWinCondition($game);

        expect($winner)->toBe('werewolf');
    });

    it('werewolves win when they exceed village count', function () {
        $game = Game::create(['code' => 'TEST021', 'status' => 'playing', 'mode' => 'human_narrator', 'round' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'is_alive' => true, 'order_index' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf2', 'role_id' => $this->werewolfRole->id, 'is_alive' => true, 'order_index' => 2]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 3]);

        $winner = $this->engine->checkWinCondition($game);

        expect($winner)->toBe('werewolf');
    });

    it('game continues when werewolves exist but are outnumbered', function () {
        $game = Game::create(['code' => 'TEST022', 'status' => 'playing', 'mode' => 'human_narrator', 'round' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Wolf1', 'role_id' => $this->werewolfRole->id, 'is_alive' => true, 'order_index' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 2]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager2', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 3]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager3', 'role_id' => $this->villagerRole->id, 'is_alive' => true, 'order_index' => 4]);

        $winner = $this->engine->checkWinCondition($game);

        expect($winner)->toBeNull();
    });
});

describe('Role Assignment', function () {
    it('assigns roles correctly to players', function () {
        $game = Game::create(['code' => 'TEST023', 'status' => 'waiting', 'mode' => 'human_narrator']);
        $player1 = GamePlayer::create(['game_id' => $game->id, 'name' => 'P1', 'order_index' => 1, 'is_alive' => true]);
        $player2 = GamePlayer::create(['game_id' => $game->id, 'name' => 'P2', 'order_index' => 2, 'is_alive' => true]);
        $player3 = GamePlayer::create(['game_id' => $game->id, 'name' => 'P3', 'order_index' => 3, 'is_alive' => true]);

        $this->engine->assignRoles($game, ['werewolf' => 1, 'villager' => 2]);

        $player1->refresh();
        $player2->refresh();
        $player3->refresh();

        $roles = collect([$player1->role?->slug, $player2->role?->slug, $player3->role?->slug])->filter()->toArray();

        expect(count($roles))->toBe(3);
        expect(in_array('werewolf', $roles))->toBeTrue();
        expect(in_array('villager', $roles))->toBeTrue();
    });

    it('throws exception when role count does not match player count', function () {
        $game = Game::create(['code' => 'TEST024', 'status' => 'waiting', 'mode' => 'human_narrator']);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'P1', 'order_index' => 1, 'is_alive' => true]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'P2', 'order_index' => 2, 'is_alive' => true]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'P3', 'order_index' => 3, 'is_alive' => true]);

        expect(fn() => $this->engine->assignRoles($game, ['werewolf' => 1]))->toThrow(\InvalidArgumentException::class);
    });
});

describe('Night Actions Completion', function () {
    it('night actions complete with only villagers', function () {
        $game = Game::create(['code' => 'TEST027', 'status' => 'playing', 'current_phase' => 'night', 'mode' => 'human_narrator', 'round' => 1]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager1', 'role_id' => $this->villagerRole->id, 'order_index' => 1, 'is_alive' => true]);
        GamePlayer::create(['game_id' => $game->id, 'name' => 'Villager2', 'role_id' => $this->villagerRole->id, 'order_index' => 2, 'is_alive' => true]);

        expect($this->engine->allNightActionsComplete($game))->toBeTrue();
    });
});