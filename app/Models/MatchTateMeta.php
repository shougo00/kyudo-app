<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchTateMeta extends Model
{
    protected $fillable = [
        'match_team_id',
        'date',
        'tate_no',
        'elapsed_seconds',
    ];

    public function team()
    {
        return $this->belongsTo(MatchTeam::class, 'match_team_id');
    }
}
