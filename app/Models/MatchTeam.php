<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchTeam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'group_id',
        'date',
        'name',
        'division',
        'tate_size',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function members()
    {
        return $this->hasMany(MatchTeamMember::class);
    }

    public function records()
    {
        return $this->hasMany(Record::class);
    }

    public function tateMetas()
    {
        return $this->hasMany(MatchTateMeta::class);
    }
}
