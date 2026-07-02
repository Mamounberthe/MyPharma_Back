<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePushTokenRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Rôle forcé : l'inscription publique ne crée que des clients.
        // 'role' n'étant pas mass-assignable, on l'assigne explicitement via forceFill.
        // (Empêche l'élévation de privilèges via un champ "role" dans la requête.)
        $user->forceFill(['role' => 'client'])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function updatePushToken(UpdatePushTokenRequest $request)
    {
        $request->user()->update([
            'expo_push_token' => $request->push_token
        ]);

        return response()->json(['message' => 'Push token updated successfully']);
    }

    public function update(UpdateUserRequest $request)
    {
        $request->user()->update($request->only(['name', 'email']));

        return response()->json(['user' => $request->user()->fresh(), 'message' => 'Profil mis à jour.']);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        if (! Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
        }

        $request->user()->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        // On déclenche l'envoi sans révéler si l'email existe (anti-énumération
        // de comptes) : la réponse est identique dans tous les cas.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Si un compte est associé à cette adresse, un lien de réinitialisation vient d’être envoyé.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
        }

        return response()->json(['message' => 'Token invalide ou expiré.'], 422);
    }
}
