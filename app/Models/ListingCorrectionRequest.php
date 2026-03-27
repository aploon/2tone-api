<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingCorrectionRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'listing_id',
        'owner_id',
        'admin_id',
        'title',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'listing_id' => 'integer',
            'owner_id' => 'integer',
            'admin_id' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}

