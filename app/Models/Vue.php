<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vue extends Model
{
    protected $table = 'vues';

    protected $fillable = ['annonce_id', 'utilisateur_id', 'ip', 'date_vue'];

    protected function casts(): array
    {
        return [
            'date_vue' => 'datetime',
        ];
    }

    public function annonce(): BelongsTo
    {
        return $this->belongsTo(Annonce::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
