<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    protected $fillable = [
        'group_name',
        'representative_name',
        'email',
        'ip_address',
        'user_agent',
    ];
}
