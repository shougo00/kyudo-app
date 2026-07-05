<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shot extends Model
{
    protected $fillable = [
        'record_id',
        'shot_no',
        'result',
        'numeric_score',
    ];

    public function record()
    {
        return $this->belongsTo(Record::class);
    }
}
