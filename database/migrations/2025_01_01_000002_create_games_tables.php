<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->string('status'); // waiting, playing, finished
            $table->string('mode'); // human_narrator, auto_narrator
            $table->string('current_phase')->nullable(); // night, day, voting
            $table->timestamp('phase_ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('round')->default(0);
            $table->timestamps();
        });

        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_host')->default(false);
            $table->boolean('is_alive')->default(true);
            $table->boolean('is_ai')->default(false);
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('game_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('game_players')->cascadeOnDelete();
            $table->string('type'); // kill, inspect, save, vote
            $table->foreignId('target_player_id')->nullable()->constrained('game_players')->cascadeOnDelete();
            $table->string('phase'); // night, day, voting
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('game_players')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('game_players')->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->timestamps();
        });

        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // phase_change, death, role_reveal, announcement
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('game_state_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->json('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_state_snapshots');
        Schema::dropIfExists('game_events');
        Schema::dropIfExists('votes');
        Schema::dropIfExists('game_actions');
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('games');
    }
};
