<?php

namespace App\Models;
use App\Models\Group;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
  // app/Models/User.php
    protected $fillable = [
        'name',
        'registration_license_code_id',
        'username',
        'email',
        'password',
        'is_admin',
        'level',
        'exp',
        'next_exp',
        'point',
        'gender',
        'grade_level',
        'all_absent',
        'attendance_weekdays',
        'official_record_height_extra',
        'match_record_height_extra',
        'uses_camera',
        'lineup_pool_height_level',
        'line_user_id',
        'line_link_code',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'grade_level' => 'integer',
            'all_absent' => 'boolean',
            'attendance_weekdays' => 'array',
            'uses_camera' => 'boolean',
            'lineup_pool_height_level' => 'integer',
        ];
    }

    public function isDefaultAbsentForDate(string $date): bool
    {
        if ($this->all_absent) {
            return true;
        }

        $weekdays = $this->attendance_weekdays;

        if (!is_array($weekdays) || count($weekdays) === 0) {
            return false;
        }

        $dayOfWeek = (int) \Carbon\Carbon::parse($date)->dayOfWeek;

        return !in_array($dayOfWeek, array_map('intval', $weekdays), true);
    }
        // app/Models/User.php
    public function avatar()
    {
        return $this->hasOne(Avatar::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->wherePivotNull('deleted_at');
    }

    public function registrationLicenseCode()
    {
        return $this->belongsTo(RegistrationLicenseCode::class, 'registration_license_code_id');
    }

}
