<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lineup extends Model
{
    protected $fillable = [
        'group_id',
        'date',
        'tate_size'
    ];

    public function members()
    {
        return $this->hasMany(LineupMember::class);
    }
}
