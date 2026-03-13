<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quartier extends Model
{
    protected $fillable = ['nom', 'ville'];

    protected function casts(): array
    {
        return [];
    }

    public function annonces(): HasMany
    {
        return $this->hasMany(Annonce::class, 'quartier_id');
    }
}
