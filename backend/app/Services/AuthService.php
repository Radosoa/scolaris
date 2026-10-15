<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Vérifie les identifiants et émet un token Sanctum.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->actif) {
            throw ValidationException::withMessages(['email' => 'Ce compte est désactivé. Contactez l\'administration.']);
        }

        $user->forceFill(['derniere_connexion_at' => now()])->save();

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    /**
     * Révoque uniquement le token utilisé pour la requête courante.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
