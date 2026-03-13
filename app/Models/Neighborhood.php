<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    protected $fillable = ['name', 'city'];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'neighborhood_id');
    }
}
