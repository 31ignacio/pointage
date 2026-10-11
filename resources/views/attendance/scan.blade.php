@extends('layouts.app')

@section('title', 'Pointage')

@push('styles')
    <style>
        .pointage-page {
            min-height: calc(100vh - 56px);
            display: flex;
            align-items: center;
            background: radial-gradient(circle at top left, #eafaf1 0%, transparent 55%),
                radial-gradient(circle at bottom right, #eaf2fb 0%, transparent 55%),
                #f4f6f9;
        }

        .pointage-card {
            border-radius: 1.75rem;
            border: 1px solid rgba(0, 0, 0, .04);
            box-shadow: 0 20px 45px -20px rgba(15, 23, 42, .25);
        }

        .pointage-avatar {
            width: 60px;
            height: 60px;
            margin: 0 auto .9rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            background: linear-gradient(135deg, #34d399, #059669);
            box-shadow: 0 8px 18px -6px rgba(5, 150, 105, .55);
        }

        .site-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: #eef2ff;
            color: #4338ca;
            padding: .35rem .85rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: .82rem;
        }

        .last-action-box {
            background: #f8fafc;
            border: 1px dashed #e2e8f0;
            border-radius: 1rem;
            padding: .65rem 1rem;
            font-size: .9rem;
        }

        #geo-status.geo-status {
            border-radius: 999px;
            font-weight: 600;
            font-size: .88rem;
            padding: .6rem 1rem;
            border: none;
        }

        #geo-details {
            background: #f8fafc;
            border-radius: .85rem;
            padding: .6rem .85rem;
            line-height: 1.5;
        }

        .btn-pointage {
            border: none;
            border-radius: 999px;
            padding: .95rem 1.5rem;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .01em;
            color: #fff;
            transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
        }

        .btn-pointage:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .btn-pointage:disabled {
            opacity: .55;
        }

        .btn-pointage.arrival {
            background: linear-gradient(135deg, #34d399, #059669);
            box-shadow: 0 10px 22px -10px rgba(5, 150, 105, .6);
        }

        .btn-pointage.departure {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            box-shadow: 0 10px 22px -10px rgba(217, 119, 6, .6);
        }

        .history-link {
            text-decoration: none;
            font-weight: 600;
            font-size: .9rem;
        }
    </style>
@endpush

@section('content')
    <div class="pointage-page">
        <div class="row justify-content-center w-100">
            <div class="col-12 col-sm-8 col-md-6 col-lg-5">
                <div class="card pointage-card">
                    <div class="card-body p-4 p-md-5 text-center">
                        <div class="pointage-avatar">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>

                        <h1 class="h5 mb-1">Bonjour {{ auth()->user()->name }}</h1>
                        <p class="text-muted small mb-4">Prêt à enregistrer votre présence ?</p>

                        @if ($error)
                            <span class="d-none" data-toast-message="{{ $error }}" data-toast-type="error"></span>
                        @else
                            <div class="mb-3">
                                <span class="site-chip">
                                    <i class="bi bi-geo"></i> {{ $site->name }}
                                </span>
                            </div>

                            @if ($lastRecord)
                                <div class="last-action-box text-muted mb-4">
                                    <i class="bi bi-clock-history"></i>
                                    Dernière action :
                                    <strong class="text-dark">{{ $lastRecord->type === 'arrival' ? 'Arrivée' : 'Sortie' }}</strong>
                                    à {{ $lastRecord->recorded_at->format('H:i') }}
                                </div>
                            @else
                                <div class="last-action-box text-muted mb-4">
                                    <i class="bi bi-moon-stars"></i> Aucun pointage aujourd'hui.
                                </div>
                            @endif

                            <div id="geo-status" class="geo-status mb-2">
                                <i class="bi bi-geo-alt"></i> Localisation en attente…
                            </div>
                            <button type="button" id="geo-retry" class="btn btn-sm btn-outline-secondary mb-3" hidden>Réessayer la localisation</button>

                            <div id="geo-details" class="small text-muted mb-4 d-none" aria-live="polite"></div>

                            <button id="btn-check"
                                class="btn btn-lg w-100 btn-pointage {{ $nextAction === 'arrival' ? 'arrival' : 'departure' }}"
                                disabled>
                                <i class="bi {{ $nextAction === 'arrival' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }}"></i>
                                ENREGISTRER {{ $nextAction === 'arrival' ? 'MON ARRIVÉE' : 'MA SORTIE' }}
                            </button>

                            <div id="result" class="mt-3"></div>
                            @if (auth()->user()->role === 'employe' && !($employee?->device_fallback_enabled ?? false) && !($passkeyEnabled ?? false))
                                <p class="small text-muted mt-3 mb-0">Activez votre empreinte pour pointer. <a href="{{ route('passkeys.activate') }}">Saisir un code d’activation</a> (à demander à votre administrateur).</p>
                            @endif
                        @endif

                        <a href="{{ route('attendance.history') }}" class="history-link d-inline-block mt-4">
                            <i class="bi bi-clock-history"></i> Voir mon historique
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="deviceChangeModal" tabindex="-1" aria-labelledby="deviceChangeTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="deviceChangeTitle"><i class="bi bi-phone me-2 text-warning"></i>Nouvel appareil détecté</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Vous essayez d’effectuer votre pointage depuis un appareil différent de votre appareil habituel.</p>
                    <p class="mb-0">Voulez-vous demander l’autorisation d’utiliser cet appareil ? Aucun pointage ne sera enregistré avant la validation de l’administrateur.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirm-device-change">Oui, demander le changement</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('btn-check');
            const geoStatus = document.getElementById('geo-status');
            const geoDetails = document.getElementById('geo-details');
            const deviceModalElement = document.getElementById('deviceChangeModal');
            const deviceModal = deviceModalElement ? window.bootstrap.Modal.getOrCreateInstance(deviceModalElement) : null;
            const confirmDeviceChange = document.getElementById('confirm-device-change');
            const geoRetry = document.getElementById('geo-retry');
            const requiresPasskey = @json(auth()->user()->role === 'employe' && !($employee?->device_fallback_enabled ?? false));

            if (!btn) return;

            const pointageButtonMarkup = btn.innerHTML;
            let currentPosition = null;
            let pendingDeviceId = null;

            function setRetryState() {
                btn.disabled = !currentPosition;
                btn.innerHTML = pointageButtonMarkup;
            }

            function submitDeviceChange() {
                confirmDeviceChange.disabled = true;
                fetch("{{ route('attendance.device.request') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ device_id: pendingDeviceId }),
                }).then(async (res) => ({ ok: res.ok, data: await res.json() }))
                    .then(({ ok, data }) => {
                        if (!ok || !data.ok) throw new Error(data.message || 'La demande n’a pas pu être envoyée.');
                        deviceModal.hide();
                        showToast(data.message, 'success');
                        setRetryState();
                    }).catch((error) => {
                        showToast(error.message || 'Erreur réseau. Réessayez.', 'error');
                        setRetryState();
                    }).finally(() => { confirmDeviceChange.disabled = false; });
            }

            confirmDeviceChange?.addEventListener('click', submitDeviceChange);

            function getOrCreateDeviceId() {
                let id = localStorage.getItem('pointage_device_id');
                if (!id) {
                    id = 'dev-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
                    localStorage.setItem('pointage_device_id', id);
                }
                return id;
            }

            function showLocationError(message) {
                geoStatus.className = 'geo-status geo-error';
                geoStatus.textContent = message;
                geoRetry.hidden = false;
                btn.disabled = true;
                showToast(message, 'error');
            }

            async function requestLocation() {
                geoRetry.hidden = true;
                btn.disabled = true;
                if (!('geolocation' in navigator)) {
                    showLocationError('Ce navigateur ne prend pas en charge la localisation. Essayez un navigateur récent.');
                    return;
                }

                if (navigator.permissions?.query) {
                    try {
                        const permission = await navigator.permissions.query({ name: 'geolocation' });
                        if (permission.state === 'denied') {
                            showLocationError('Localisation refusée. Dans les réglages du navigateur, autorisez la localisation pour ce site, puis activez la localisation dans les réglages du téléphone.');
                            return;
                        }
                    } catch (_) {
                        // Certains navigateurs ne permettent pas de consulter cette permission.
                    }
                }

                geoStatus.className = 'geo-status';
                geoStatus.textContent = 'Demande de localisation en cours…';

                navigator.geolocation.getCurrentPosition(function(pos) {
                    currentPosition = pos.coords;
                    geoStatus.className = 'geo-status geo-success';
                    geoStatus.textContent = 'Position vérifiée';
                    geoDetails.classList.remove('d-none');
                    geoDetails.textContent = 'Latitude : ' + currentPosition.latitude.toFixed(6) +
                        ' · Longitude : ' + currentPosition.longitude.toFixed(6) +
                        ' · Précision estimée : ±' + Math.round(currentPosition.accuracy) + ' m';
                    const mapLink = document.createElement('a');
                    mapLink.href = 'https://www.google.com/maps?q=' + currentPosition.latitude + ',' + currentPosition.longitude;
                    mapLink.target = '_blank';
                    mapLink.rel = 'noopener';
                    mapLink.textContent = 'Voir ma position sur Google Maps';
                    geoDetails.append(document.createElement('br'), mapLink);
                    geoRetry.hidden = true;
                    btn.disabled = false;
                }, function(err) {
                    const message = err.code === 1
                        ? 'Localisation refusée. Autorisez-la dans les réglages du navigateur pour ce site, puis dans les réglages de localisation du téléphone.'
                        : err.code === 2
                            ? 'Position indisponible. Vérifiez que la localisation du téléphone est activée, puis réessayez.'
                            : 'La localisation met trop de temps à répondre. Vérifiez les réglages du téléphone et réessayez.';
                    showLocationError(message);
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                });
            }

            geoRetry.addEventListener('click', requestLocation);

            btn.addEventListener('click', function() {
                if (!currentPosition) return;

                btn.disabled = true;
                btn.innerHTML = 'Enregistrement…';

                const verifyPasskey = requiresPasskey
                    ? window.Passkeys.verify({
                        routes: {
                            options: "{{ route('attendance.passkey.options') }}",
                            submit: "{{ route('attendance.passkey.verify') }}",
                        },
                    })
                    : Promise.resolve({ ok: true });

                verifyPasskey.then(function() {
                    return fetch("{{ route('attendance.check') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            latitude: currentPosition.latitude,
                            longitude: currentPosition.longitude,
                            device_id: getOrCreateDeviceId(),
                        }),
                    }).then(async (res) => ({ status: res.status, data: await res.json() }));
                }).then(function(r) {
                    if (r.data.device_change_required) {
                        pendingDeviceId = getOrCreateDeviceId();
                        if (r.data.device_change_rejected) showToast('Votre demande précédente a été refusée. Vous pouvez soumettre une nouvelle demande si nécessaire.', 'error');
                        deviceModal.show();
                        setRetryState();
                    } else if (r.data.ok) {
                        showToast(r.data.message, 'success');
                        setTimeout(function() { window.location.reload(); }, 1200);
                    } else {
                        showToast(r.data.message || 'Le pointage n’a pas pu être enregistré.', 'error');
                        setRetryState();
                    }
                }).catch(function(error) {
                    const message = error?.name === 'UserCancelledError'
                        ? 'Empreinte refusée ou annulée. Réessayez pour pointer.'
                        : error?.name === 'NotSupportedError'
                            ? 'Cet appareil ou ce navigateur ne prend pas en charge les passkeys.'
                            : (error?.message || 'Vérification de l’empreinte impossible. Réessayez.');
                    if (requiresPasskey) {
                        fetch("{{ route('attendance.passkey.failure') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ reason: error?.name === 'UserCancelledError' ? 'annulee' : error?.name === 'NotSupportedError' ? 'non_supportee' : 'echec' }),
                        }).catch(() => {});
                    }
                    showToast(message, 'error');
                    setRetryState();
                });
            });

            requestLocation();
        });
    </script>
@endpush
