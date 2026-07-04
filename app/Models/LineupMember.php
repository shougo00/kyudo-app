<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineupMember extends Model
{
    protected $fillable = [
        'lineup_id',
        'user_id',
        'position',
        'is_absent',
        'is_late',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lineup()
    {
        return $this->belongsTo(Lineup::class);
    }
}