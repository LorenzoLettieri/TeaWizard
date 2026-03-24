<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'date',
        'user_id',
        'platform',
        'deck_id',
        'opponent_deck',
        'dice_result',
        'game_1_result',
        'game_2_result',
        'game_3_result',
        'match_result',
        'notes',
        'variance',
        'gameplan',
        'sideboard_notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }
}
