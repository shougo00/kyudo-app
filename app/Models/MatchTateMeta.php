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
        'is_timer_running',
        'timer_started_at',
        'scoring_mode',
    ];

    protected $casts = [
        'is_timer_running' => 'boolean',
        'timer_started_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(MatchTeam::class, 'match_team_id');
    }
}
