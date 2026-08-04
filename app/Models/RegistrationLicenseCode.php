<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationLicenseCode extends Model
{
    protected $fillable = [
        'code',
        'memo',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'registration_license_code_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
