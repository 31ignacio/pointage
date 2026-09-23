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

        #geo-status.alert {
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
                            <div class="alert alert-danger">{{ $error }}</div>
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

                            <div id="geo-status" class="alert alert-secondary mb-2">
                                <i class="bi bi-geo-alt"></i> Localisation en attente…
                            </div>

                            <div id="geo-details" class="small text-muted mb-4 d-none" aria-live="polite"></div>

                            <button id="btn-check"
                                class="btn btn-lg w-100 btn-pointage {{ $nextAction === 'arrival' ? 'arrival' : 'departure' }}"
                                disabled>
                                <i class="bi {{ $nextAction === 'arrival' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }}"></i>
                                ENREGISTRER {{ $nextAction === 'arrival' ? 'MON ARRIVÉE' : 'MA SORTIE' }}
                            </button>

                            <div id="result" class="mt-3"></div>
                        @endif

                        <a href="{{ route('attendance.history') }}" class="history-link d-inline-block mt-4">
                            <i class="bi bi-clock-history"></i> Voir mon historique
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const btn = document.getElementById('btn-check');
            const geoStatus = document.getElementById('geo-status');
            const geoDetails = document.getElementById('geo-details');
            const result = document.getElementById('result');

            if (!btn) return;

            let currentPosition = null;

            function getOrCreateDeviceId() {
                let id = localStorage.getItem('pointage_device_id');
                if (!id) {
                    id = 'dev-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
                    localStorage.setItem('pointage_device_id', id);
                }
                return id;
            }

            function requestLocation() {
                if (!('geolocation' in navigator)) {
                    geoStatus.className = 'alert alert-danger';
                    geoStatus.innerHTML = "Votre navigateur ne supporte pas la géolocalisation.";
                    return;
                }

                navigator.geolocation.getCurrentPosition(function(pos) {
                    currentPosition = pos.coords;
                    geoStatus.className = 'alert alert-success';
                    geoStatus.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Position vérifiée';
                    geoDetails.classList.remove('d-none');
                    geoDetails.innerHTML = 'Latitude : <code>' + currentPosition.latitude.toFixed(6) +
                        '</code> · Longitude : <code>' + currentPosition.longitude.toFixed(6) +
                        '</code> · Précision estimée : <code>±' + Math.round(currentPosition.accuracy) +
                        ' m</code><br><a href="https://www.google.com/maps?q=' +
                        currentPosition.latitude + ',' + currentPosition.longitude +
                        '" target="_blank" rel="noopener">Voir ma position sur Google Maps</a>';
                    btn.disabled = false;
                }, function(err) {
                    geoStatus.className = 'alert alert-danger';
                    const reasons = {
                        1: 'autorisation refusée',
                        2: 'position indisponible',
                        3: 'délai dépassé',
                    };
                    geoStatus.innerHTML = "Localisation " + (reasons[err.code] || 'indisponible') +
                        ". Autorisez la position dans votre navigateur et utilisez HTTPS (ou localhost).";
                    btn.disabled = true;
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                });
            }

            btn.addEventListener('click', function() {
                if (!currentPosition) return;

                btn.disabled = true;
                btn.innerHTML = 'Enregistrement…';

                fetch("{{ route('attendance.check') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            latitude: currentPosition.latitude,
                            longitude: currentPosition.longitude,
                            device_id: getOrCreateDeviceId(),
                        }),
                    })
                    .then(function(res) {
                        return res.json().then(function(data) {
                            return {
                                status: res.status,
                                data: data
                            };
                        });
                    })
                    .then(function(r) {
                        if (r.data.ok) {
                            result.innerHTML = '<div class="alert alert-success">' + r.data.message +
                                '</div>';
                            setTimeout(function() {
                                window.location.reload();
                            }, 1200);
                        } else {
                            result.innerHTML = '<div class="alert alert-danger">' + r.data.message +
                                '</div>';
                            btn.disabled = false;
                            btn.innerHTML = 'RÉESSAYER';
                        }
                    })
                    .catch(function() {
                        result.innerHTML =
                        '<div class="alert alert-danger">Erreur réseau. Réessayez.</div>';
                        btn.disabled = false;
                        btn.innerHTML = 'RÉESSAYER';
                    });
            });

            requestLocation();
        })();
    </script>
@endpush