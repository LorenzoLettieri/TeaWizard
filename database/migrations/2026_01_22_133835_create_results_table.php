<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('platform');
            $table->foreignId('deck_id')->constrained()->onDelete('cascade');
            $table->string('opponent_deck');
            $table->string('dice_result'); // won, lost
            $table->string('game_1_result'); // win, loss, draw
            $table->string('game_2_result')->nullable();
            $table->string('game_3_result')->nullable();
            $table->string('match_result'); // win, loss, draw
            $table->text('notes')->nullable();
            $table->text('variance')->nullable();
            $table->text('gameplan')->nullable();
            $table->text('sideboard_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
