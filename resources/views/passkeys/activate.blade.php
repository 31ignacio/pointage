@extends('layouts.app')

@section('title', 'Activer mon empreinte')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="page-heading">
                <div>
                    <div class="page-kicker">Sécurité du pointage</div>
                    <h1 class="mb-1">Activer mon empreinte</h1>
                    <p class="text-muted mb-0">Enregistrez une passkey sur cet appareil pour confirmer vos pointages.</p>
                </div>
            </div>

            <div class="card p-4 p-md-5">
                @if ($hasPasskey)
                    <p class="small text-muted mb-3">Une passkey est déjà enregistrée pour votre compte. Si vous changez de téléphone, demandez à votre administrateur de la réinitialiser et de vous fournir un nouveau code.</p>
                @endif

                @if ($activationReady)
                    <div class="mb-3">
                        <label class="form-label" for="passkey-name">Nom de l’appareil</label>
                        <input id="passkey-name" class="form-control" maxlength="255" value="Téléphone de {{ $employee->first_name }}">
                    </div>
                    <button type="button" id="register-passkey" class="btn btn-primary w-100" @disabled($hasPasskey)>
                        <i class="bi bi-fingerprint me-2"></i>Enregistrer mon empreinte
                    </button>
                @else
                    <p class="text-muted">Saisissez le code à usage unique que votre administrateur vous a remis. Il est valable 24 heures.</p>
                    <form id="activation-code-form">
                        <label class="form-label" for="activation-code">Code d’activation</label>
                        <input id="activation-code" name="code" class="form-control form-control-lg font-monospace text-uppercase mb-3" autocomplete="one-time-code" required maxlength="40">
                        <button class="btn btn-primary w-100" type="submit">Vérifier le code</button>
                    </form>
                    @if ($hasPasskey)
                        <p class="small text-muted mt-3 mb-0">Un nouveau code sera nécessaire pour remplacer la passkey existante. Contactez votre administrateur.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const codeForm = document.getElementById('activation-code-form');
            const registerButton = document.getElementById('register-passkey');

            async function postJson(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(body),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'La demande a échoué.');
                return data;
            }

            codeForm?.addEventListener('submit', async function (event) {
                event.preventDefault();
                const submitButton = codeForm.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                try {
                    const data = await postJson("{{ route('passkeys.activation-code') }}", {
                        code: document.getElementById('activation-code').value,
                    });
                    showToast(data.message, 'success');
                    window.location.reload();
                } catch (error) {
                    showToast(error.message || 'Code invalide ou expiré.', 'error');
                    submitButton.disabled = false;
                }
            });

            registerButton?.addEventListener('click', async function () {
                if (!window.Passkeys.isSupported()) {
                    showToast('Ce navigateur ou cet appareil ne prend pas en charge les passkeys. Contactez votre administrateur.', 'error');
                    return;
                }

                registerButton.disabled = true;
                try {
                    const result = await window.Passkeys.register({
                        name: document.getElementById('passkey-name').value || 'Téléphone',
                        routes: {
                            options: "{{ route('passkeys.registration-options') }}",
                            submit: "{{ route('passkeys.registration-store') }}",
                        },
                    });
                    showToast(result.message || 'Empreinte activée avec succès.', 'success');
                    setTimeout(() => window.location.assign("{{ route('attendance.scan') }}"), 1200);
                } catch (error) {
                    const message = error?.name === 'UserCancelledError'
                        ? 'Enregistrement de l’empreinte annulé.'
                        : error?.name === 'NotSupportedError'
                            ? 'Cet appareil ou ce navigateur ne prend pas en charge les passkeys.'
                            : (error?.message || 'Impossible d’enregistrer cette empreinte. Réessayez.');
                    showToast(message, 'error');
                    registerButton.disabled = false;
                }
            });
        });
    </script>
@endpush
