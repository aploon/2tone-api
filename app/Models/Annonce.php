<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Annonce extends Model
{
    public const TYPE_VILLA = 'Villa';
    public const TYPE_MAISON = 'Maison';
    public const TYPE_APPARTEMENT = 'Appartement';
    public const TYPE_DUPLEX_TRIPLEX = 'Duplex/Triplex';
    public const TYPE_IMMEUBLE = 'Immeuble';
    public const TYPE_STUDIO = 'Studio';
    public const TYPE_BUREAU = 'Bureau';
    public const TYPE_TERRAIN = 'Terrain';

    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_PAYEE = 'payee';
    public const STATUT_PUBLIEE = 'publiee';
    public const STATUT_REJETEE = 'rejetee';

    protected $fillable = [
        'proprietaire_id',
        'quartier_id',
        'titre',
        'description',
        'type_bien',
        'prix',
        'statut_publication',
        'chambres',
        'salles_de_bain',
        'surface_m2',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'integer',
            'chambres' => 'integer',
            'salles_de_bain' => 'integer',
            'surface_m2' => 'integer',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class);
    }

    public function medias(): HasMany
    {
        return $this->hasMany(Media::class, 'annonce_id')->orderBy('ordre');
    }

    public function paiement(): HasOne
    {
        return $this->hasOne(Paiement::class, 'annonce_id');
    }

    public function favoris(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favoris', 'annonce_id', 'utilisateur_id')
            ->withTimestamps();
    }

    public function vues(): HasMany
    {
        return $this->hasMany(Vue::class, 'annonce_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'annonce_id');
    }

    public function scopeVisible($query)
    {
        return $query->whereIn('statut_publication', [self::STATUT_PAYEE, self::STATUT_PUBLIEE]);
    }

    public function hasVideo3d(): bool
    {
        return $this->medias()->where('type', 'video_3d')->exists();
    }
}
