<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $table = 'otps';

    protected $fillable = ['telephone', 'code', 'expiration', 'valide'];

    protected function casts(): array
    {
        return [
            'expiration' => 'datetime',
            'valide' => 'boolean',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expiration->isPast();
    }
}
