<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleAction;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $werewolf = Role::create([
            'name' => 'Werewolf',
            'slug' => 'werewolf',
            'faction' => 'werewolf',
            'description' => 'A cunning predator hiding among the village. Each night, coordinate with your pack to eliminate a villager.',
            'night_order' => 1,
            'max_uses' => null,
        ]);

        RoleAction::create([
            'role_id' => $werewolf->id,
            'action_type' => 'kill',
            'target_type' => 'player',
            'phase' => 'night',
        ]);

        $seer = Role::create([
            'name' => 'Seer',
            'slug' => 'seer',
            'faction' => 'village',
            'description' => 'A visionary with the gift of sight. Each night, you may glimpse the true nature of one player.',
            'night_order' => 2,
            'max_uses' => null,
        ]);

        RoleAction::create([
            'role_id' => $seer->id,
            'action_type' => 'inspect',
            'target_type' => 'player',
            'phase' => 'night',
        ]);

        $witch = Role::create([
            'name' => 'Witch',
            'slug' => 'witch',
            'faction' => 'village',
            'description' => 'A keeper of ancient potions. You hold one life-saving potion and one killing potion — use them wisely.',
            'night_order' => 3,
            'max_uses' => json_encode(['save' => 1, 'kill' => 1]),
        ]);

        RoleAction::create([
            'role_id' => $witch->id,
            'action_type' => 'save',
            'target_type' => 'player',
            'phase' => 'night',
        ]);

        RoleAction::create([
            'role_id' => $witch->id,
            'action_type' => 'kill',
            'target_type' => 'player',
            'phase' => 'night',
        ]);

        Role::create([
            'name' => 'Villager',
            'slug' => 'villager',
            'faction' => 'village',
            'description' => 'An ordinary villager with no special abilities. Your voice and intuition are your only weapons.',
            'night_order' => null,
            'max_uses' => null,
        ]);
    }
}
