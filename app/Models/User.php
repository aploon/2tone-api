<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_LOCATAIRE = 'locataire';
    public const ROLE_PROPRIETAIRE = 'proprietaire';
    public const ROLE_ADMIN = 'admin';

    public const STATUT_ACTIF = 'actif';
    public const STATUT_SUSPENDU = 'suspendu';

    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'numero_whatsapp',
        'nom',
        'role',
        'statut',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function annonces(): HasMany
    {
        return $this->hasMany(Annonce::class, 'proprietaire_id');
    }

    public function favoris(): BelongsToMany
    {
        return $this->belongsToMany(Annonce::class, 'favoris', 'utilisateur_id', 'annonce_id')
            ->withTimestamps();
    }

    public function vues(): HasMany
    {
        return $this->hasMany(Vue::class, 'utilisateur_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'utilisateur_id');
    }

    public function isProprietaire(): bool
    {
        return $this->role === self::ROLE_PROPRIETAIRE || $this->role === self::ROLE_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
