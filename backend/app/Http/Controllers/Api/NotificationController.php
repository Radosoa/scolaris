<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/** Notifications de l'utilisateur connecté (cloche de l'en-tête). */
class NotificationController extends Controller
{
    /** Les 20 plus récentes et le nombre de non lues. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->limit(20)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'titre' => $n->data['titre'] ?? '',
                'message' => $n->data['message'] ?? '',
                'lien' => $n->data['lien'] ?? null,
                'lu' => $n->read_at !== null,
                'cree_le' => $n->created_at->toIso8601String(),
            ]),
            'meta' => ['non_lues' => $user->unreadNotifications()->count()],
        ]);
    }

    public function lire(Request $request, string $id): Response
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->noContent();
    }

    public function toutLire(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
