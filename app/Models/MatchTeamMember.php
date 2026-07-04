<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchTeamMember extends Model
{
    protected $fillable = [
        'match_team_id',
        'date',
        'user_id',
        'tate_no',
        'position',
        'is_absent',
        'is_late',
    ];

    public function team()
    {
        return $this->belongsTo(MatchTeam::class, 'match_team_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
