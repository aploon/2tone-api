<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyMedia extends Model
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO_3D = 'video_3d';

    protected $table = 'property_media';

    protected $fillable = ['listing_id', 'type', 'url', 'size_bytes', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
