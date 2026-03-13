<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO_3D = 'video_3d';

    protected $table = 'medias';

    protected $fillable = ['annonce_id', 'type', 'url', 'poids', 'main', 'ordre'];

    protected function casts(): array
    {
        return [
            'main' => 'boolean',
            'poids' => 'integer',
            'ordre' => 'integer',
        ];
    }

    public function annonce(): BelongsTo
    {
        return $this->belongsTo(Annonce::class);
    }
}
