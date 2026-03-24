<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model
{
    protected $fillable = [
        'user_id',
        'archetype_id',
        'name',
        'format',
        'link',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function archetype()
    {
        return $this->belongsTo(Archetype::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }
}
