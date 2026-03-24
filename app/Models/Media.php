<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'mediable_id',
        'mediable_type',
        'file_path',
        'file_name',
        'mime_type',
        'size',
        'collection_name',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }
}
