<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KyudoResult extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'kai_time',
        'right_elbow_angle',
        'right_armpit_angle',
        'left_armpit_angle',
    ];
}