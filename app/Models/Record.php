<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'tate_no',
        'practice_type',
        'official_sheet_no',
        'match_team_id',
        'lineup_position',
        'lineup_tate_size',
    ];

    public function shots()
    {
        return $this->hasMany(Shot::class)->orderBy('shot_no');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
