<?php

namespace App\Http\Controllers;

use App\Actions\GenerateEmployeePasskeyOptions;
use App\Models\AuditLog;
use App\Models\EmployeePasskeyActivationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

class EmployeePasskeyController extends Controller
{
    public function show(Request $request)
    {
        $employee = $this->employee($request);
        $activationReady = $this->activationCode($request, $employee) !== null;

        return view('passkeys.activate', [
            'employee' => $employee,
            'activationReady' => $activationReady,
            'hasPasskey' => $request->user()->hasPasskeysEnabled(),
        ]);
    }

    public function verifyActivationCode(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $codeText = Str::upper(trim($data['code']));

        $code = DB::transaction(function () use ($request, $employee, $codeText) {
            $codes = EmployeePasskeyActivationCode::where('employee_id', $employee->id)
                ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->latest('id')->lockForUpdate()->get();

            foreach ($codes as $candidate) {
                if (Hash::check($codeText, $candidate->code_hash)) {
                    return $candidate;
                }
            }

            AuditLog::log('passkey_code_activation_invalide', 'Code invalide, expiré ou déjà utilisé', $employee->id);
            return null;
        });

        if (! $code) {
            return response()->json(['ok' => false, 'message' => 'Code invalide ou expiré. Demandez un nouveau code à votre administrateur.'], 422);
        }

        $request->session()->put('passkey.activation_code_id', $code->id);
        $request->session()->forget(['passkey.registration_options', 'passkey.registration_options_created_at']);

        return response()->json(['ok' => true, 'message' => 'Code accepté. Vous pouvez enregistrer votre empreinte.']);
    }

    public function registrationOptions(Request $request, GenerateEmployeePasskeyOptions $generate): JsonResponse
    {
        $employee = $this->employee($request);
        abort_unless($this->activationCode($request, $employee), 403, 'Saisissez d’abord un code d’activation valide.');

        $options = $generate($request->user());
        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));
        $request->session()->put('passkey.registration_options_created_at', now()->timestamp);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function store(Request $request, StorePasskey $storePasskey): JsonResponse
    {
        $employee = $this->employee($request);
        $serializedOptions = $request->session()->pull('passkey.registration_options');
        $createdAt = $request->session()->pull('passkey.registration_options_created_at');

        if (! $serializedOptions || ! is_numeric($createdAt) || now()->timestamp - (int) $createdAt > 120) {
            AuditLog::log('passkey_enregistrement_echec', 'Défi absent ou expiré', $employee->id);
            return response()->json(['message' => 'La demande d’empreinte a expiré. Réessayez.'], 422);
        }

        if (! $this->activationCode($request, $employee)) {
            return response()->json(['message' => 'Le code d’activation a expiré ou a déjà été utilisé. Demandez-en un nouveau à votre administrateur.'], 403);
        }

        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            AuditLog::log('passkey_enregistrement_echec', 'Réponse WebAuthn mal formée', $employee->id);
            return response()->json(['message' => 'L’enregistrement de l’empreinte a échoué. Réessayez.'], 422);
        }

        try {
            $credential = WebAuthn::fromJson(
                json_encode($request->input('credential'), JSON_THROW_ON_ERROR),
                PublicKeyCredential::class
            );
            $options = WebAuthn::fromJson($serializedOptions, PublicKeyCredentialCreationOptions::class);
        } catch (Throwable) {
            AuditLog::log('passkey_enregistrement_echec', 'Réponse WebAuthn illisible', $employee->id);
            return response()->json(['message' => 'L’enregistrement de l’empreinte a échoué. Réessayez.'], 422);
        }

        try {
            $passkey = DB::transaction(function () use ($request, $employee, $credential, $options, $storePasskey) {
                $activationCode = $this->activationCode($request, $employee, lock: true);
                abort_unless($activationCode, 403, 'Le code d’activation a expiré ou a déjà été utilisé.');

                $passkey = $storePasskey($request->user(), $request->string('name')->toString(), $credential, $options);
                $activationCode->update(['used_at' => now()]);
                AuditLog::log('passkey_enregistree', "passkey_id={$passkey->id}", $employee->id);

                return $passkey;
            });
        } catch (InvalidPasskeyException) {
            AuditLog::log('passkey_enregistrement_echec', 'Attestation WebAuthn invalide', $employee->id);
            return response()->json(['message' => 'L’empreinte n’a pas pu être enregistrée. Réessayez sur un appareil compatible.'], 422);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $request->session()->forget('passkey.activation_code_id');

        return response()->json(['ok' => true, 'message' => 'Empreinte activée avec succès.', 'name' => $passkey->name]);
    }

    private function employee(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'employe' && $user->employee?->status === 'active', 403);

        return $user->employee;
    }

    private function activationCode(Request $request, $employee, bool $lock = false): ?EmployeePasskeyActivationCode
    {
        $id = $request->session()->get('passkey.activation_code_id');
        if (! $id) {
            return null;
        }

        $query = EmployeePasskeyActivationCode::whereKey($id)
            ->where('employee_id', $employee->id)
            ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
