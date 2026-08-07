<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'channel',
    'type',
    'delivery_status',
    'ad_id',
    'name',
    'company_name',
    'contact_person',
    'phone',
    'email',
    'contact',
    'comment',
    'source_url',
    'utm',
    'captcha_verified',
    'created_by_user_id',
])]
class Lead extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'captcha_verified' => 'boolean',
        ];
    }
}
