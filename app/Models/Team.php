<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function decks()
    {
        return $this->belongsToMany(Deck::class)->withTimestamps();
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function logo()
    {
        return $this->morphOne(Media::class, 'mediable')->where('collection_name', 'logo');
    }
}
