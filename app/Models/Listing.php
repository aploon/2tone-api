<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    public const TYPE_VILLA = 'villa';

    public const TYPE_HOUSE = 'house';

    public const TYPE_APARTMENT = 'apartment';

    public const TYPE_DUPLEX_TRIPLEX = 'duplex_triplex';

    public const TYPE_BUILDING = 'building';

    public const TYPE_STUDIO = 'studio';

    public const TYPE_OFFICE = 'office';

    public const TYPE_LAND = 'land';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CORRECTION_REQUESTED = 'correction_requested';

    /** Nombre maximum d’images (cover + galerie) par annonce. */
    public const MAX_IMAGES_PER_LISTING = 8;

    protected $fillable = [
        'owner_id',
        'neighborhood_id',
        'title',
        'description',
        'type',
        'price',
        'billing_period',
        'publication_status',
        'bedrooms',
        'bathrooms',
        'surface_sqm',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'billing_period' => BillingPeriod::class,
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'surface_sqm' => 'integer',
        ];
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_VILLA,
            self::TYPE_HOUSE,
            self::TYPE_APARTMENT,
            self::TYPE_DUPLEX_TRIPLEX,
            self::TYPE_BUILDING,
            self::TYPE_STUDIO,
            self::TYPE_OFFICE,
            self::TYPE_LAND,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'listing_id')->orderBy('sort_order');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'listing_id');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites', 'listing_id', 'user_id')
            ->withTimestamps();
    }

    public function views(): HasMany
    {
        return $this->hasMany(ListingView::class, 'listing_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'listing_id');
    }

    public function scopeVisible($query)
    {
        return $query->whereIn('publication_status', [self::STATUS_PAID, self::STATUS_PUBLISHED]);
    }

    public function hasVideo3d(): bool
    {
        return $this->media()->where('type', 'video_3d')->exists();
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(ListingCorrectionRequest::class, 'listing_id')->orderByDesc('created_at');
    }
}
