<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $table = 'otp_codes';

    protected $fillable = ['telephone', 'code', 'expires_at', 'is_valid'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_valid' => 'boolean',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
