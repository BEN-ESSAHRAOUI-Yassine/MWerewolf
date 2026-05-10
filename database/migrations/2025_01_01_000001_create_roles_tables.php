<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('faction'); // village, werewolf, neutral
            $table->text('description');
            $table->unsignedTinyInteger('night_order')->nullable();
            $table->json('max_uses')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('role_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('action_type'); // inspect, kill, save, etc.
            $table->string('target_type'); // player, self, none
            $table->string('phase'); // night, day, voting
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_actions');
        Schema::dropIfExists('roles');
    }
};
