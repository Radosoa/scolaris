<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Les rôles spatie sont rattachés au guard « web » ; l'authentification
     * API passe par Sanctum qui résout le même modèle User.
     */
    protected string $guard_name = 'web';

    /**
     * Profil élève rattaché au compte (rôle « eleve »).
     *
     * @return HasOne<Eleve, $this>
     */
    public function eleve(): HasOne
    {
        return $this->hasOne(Eleve::class);
    }

    /**
     * Profil parent rattaché au compte (rôle « parent »).
     *
     * @return HasOne<Tuteur, $this>
     */
    public function tuteur(): HasOne
    {
        return $this->hasOne(Tuteur::class);
    }

    /**
     * Profil enseignant rattaché au compte (rôle « professeur »).
     *
     * @return HasOne<Enseignant, $this>
     */
    public function enseignant(): HasOne
    {
        return $this->hasOne(Enseignant::class);
    }

    /** Direction ou secrétariat : accès à tous les dossiers de l'établissement. */
    public function estAdministration(): bool
    {
        return $this->hasAnyRole([Role::Admin->value, Role::Secretariat->value]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }
}
