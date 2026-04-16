<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingInterestSubmission extends Model
{
    protected $fillable = [
        'name',
        'email',
        'company',
        'message',
        'mail_sent_at',
        'mail_error',
    ];

    protected function casts(): array
    {
        return [
            'mail_sent_at' => 'datetime',
        ];
    }
}
