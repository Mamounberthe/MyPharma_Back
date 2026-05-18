<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PharmacyInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PharmacyInvitationController extends Controller
{
    /**
     * Liste des invitations (Admin uniquement)
     */
    public function index(): JsonResponse
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $invitations = PharmacyInvitation::with('inviter')->latest()->get();
        return response()->json($invitations);
    }

    /**
     * Créer et envoyer une invitation
     */
    public function store(Request $request): JsonResponse
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $request->validate([
            'email' => 'required|email|unique:pharmacy_invitations,email|unique:users,email',
            'pharmacy_name' => 'required|string|max:255',
            'phone' => 'nullable|string',
        ]);

        $invitation = PharmacyInvitation::create([
            'email' => $request->email,
            'pharmacy_name' => $request->pharmacy_name,
            'phone' => $request->phone,
            'invited_by' => Auth::id(),
            'status' => 'pending',
        ]);

        // Ici, on enverrait normalement un email
        // Mail::to($invitation->email)->send(new PharmacyInvitationMail($invitation));

        return response()->json([
            'message' => 'Invitation créée avec succès.',
            'invitation' => $invitation,
            'link' => config('app.frontend_url') . '/register/pharmacy?token=' . $invitation->token
        ], 201);
    }

    /**
     * Vérifier la validité d'un token
     */
    public function validateToken(string $token): JsonResponse
    {
        $invitation = PharmacyInvitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invitation invalide.'], 404);
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => 'expired']);
            return response()->json(['message' => 'Cette invitation a expiré.'], 410);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'Cette invitation a déjà été utilisée.'], 400);
        }

        return response()->json([
            'valid' => true,
            'invitation' => $invitation
        ]);
    }
}
