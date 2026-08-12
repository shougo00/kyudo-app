<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'host_user_id',
        'invite_code',
        'max_members',
        'official_tates_per_page',
        'show_group_records_to_members',
        'allow_members_edit_group_records',
        'show_monthly_rank_on_print',
        'uses_grades',
        'grade_count',
        'grade_colors',
        'numeric_score_options',
        'last_grade_promoted_year',
    ];

    protected $casts = [
        'max_members' => 'integer',
        'official_tates_per_page' => 'integer',
        'show_group_records_to_members' => 'boolean',
        'allow_members_edit_group_records' => 'boolean',
        'show_monthly_rank_on_print' => 'boolean',
        'uses_grades' => 'boolean',
        'grade_count' => 'integer',
        'grade_colors' => 'array',
        'numeric_score_options' => 'array',
        'last_grade_promoted_year' => 'integer',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->wherePivotNull('deleted_at');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }
}
