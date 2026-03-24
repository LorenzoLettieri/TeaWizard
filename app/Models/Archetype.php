<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Archetype extends Model
{
    protected $fillable = [
        'name',
        'format',
        'description',
    ];

    public function decks()
    {
        return $this->hasMany(Deck::class);
    }
}
