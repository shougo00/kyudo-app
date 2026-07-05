<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'host_user_id',
        'invite_code',
        'official_tates_per_page',
        'uses_grades',
        'grade_count',
        'grade_colors',
        'numeric_score_options',
        'last_grade_promoted_year',
    ];

    protected $casts = [
        'uses_grades' => 'boolean',
        'grade_count' => 'integer',
        'grade_colors' => 'array',
        'numeric_score_options' => 'array',
        'last_grade_promoted_year' => 'integer',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }
}
