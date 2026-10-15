<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Action refusée parce qu'elle violerait une règle de gestion
 * (ex. supprimer l'année active, modifier une période clôturée).
 * Rendue en HTTP 409 Conflict avec un message destiné à l'utilisateur.
 */
class RegleMetierException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
