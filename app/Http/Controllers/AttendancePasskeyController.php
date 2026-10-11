<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

class AttendancePasskeyController extends Controller
{
    public function options(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        abort_unless($user->role === 'employe' && $employee?->status === 'active', 403);

        if ($employee->device_fallback_enabled) {
            return response()->json(['message' => 'L’appareil de confiance est activé pour votre compte.'], 409);
        }

        if (! $user->hasPasskeysEnabled()) {
            AuditLog::log('pointage_refuse_passkey_absente', 'Aucune passkey enregistrée', $employee->id);
            return response()->json(['message' => 'Activez votre empreinte pour pointer. Contactez votre administrateur.'], 422);
        }

        $options = $generate($user);
        $request->session()->put('attendance.passkey.options', WebAuthn::toJson($options));
        $request->session()->put('attendance.passkey.options_created_at', now()->timestamp);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function verify(Request $request, VerifyPasskey $verify): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        abort_unless($user->role === 'employe' && $employee?->status === 'active', 403);

        $serializedOptions = $request->session()->pull('attendance.passkey.options');
        $createdAt = $request->session()->pull('attendance.passkey.options_created_at');

        if (! $serializedOptions || ! is_numeric($createdAt) || now()->timestamp - (int) $createdAt > 120) {
            AuditLog::log('pointage_echec_verification_biometrique', 'Défi absent, expiré ou déjà utilisé', $employee->id);
            return response()->json(['message' => 'La demande d’empreinte a expiré. Réessayez.'], 422);
        }

        if ($employee->device_fallback_enabled || ! $user->hasPasskeysEnabled()) {
            AuditLog::log('pointage_echec_verification_biometrique', 'Aucune passkey utilisable pour ce compte', $employee->id);
            return response()->json(['message' => 'Activez votre empreinte pour pointer. Contactez votre administrateur.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            AuditLog::log('pointage_echec_verification_biometrique', 'Réponse WebAuthn mal formée', $employee->id);
            return response()->json(['message' => 'Vérification de l’empreinte impossible. Réessayez.'], 422);
        }

        try {
            $credential = WebAuthn::fromJson(
                json_encode($request->input('credential'), JSON_THROW_ON_ERROR),
                PublicKeyCredential::class
            );
            $options = WebAuthn::fromJson($serializedOptions, PublicKeyCredentialRequestOptions::class);
        } catch (Throwable) {
            AuditLog::log('pointage_echec_verification_biometrique', 'Réponse WebAuthn illisible', $employee->id);
            return response()->json(['message' => 'Vérification de l’empreinte impossible. Réessayez.'], 422);
        }

        try {
            $verify($credential, $options, $user);
        } catch (InvalidPasskeyException) {
            AuditLog::log('pointage_echec_verification_biometrique', 'Signature, origine, défi ou vérification utilisateur invalide', $employee->id);
            return response()->json(['message' => 'Empreinte refusée. Réessayez avec votre passkey enregistrée.'], 422);
        }

        $request->session()->put('attendance.passkey_verified_at', now()->timestamp);
        $request->session()->put('attendance.passkey_verified_user_id', $user->id);

        return response()->json(['ok' => true]);
    }

    public function failed(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        abort_unless($user->role === 'employe' && $employee?->status === 'active' && ! $employee->device_fallback_enabled, 403);
        $data = $request->validate(['reason' => ['required', 'in:annulee,non_supportee,echec']]);
        $request->session()->forget(['attendance.passkey.options', 'attendance.passkey.options_created_at']);
        AuditLog::log('pointage_echec_verification_biometrique', "échec navigateur={$data['reason']}", $employee->id);

        return response()->json(['ok' => true]);
    }
}
