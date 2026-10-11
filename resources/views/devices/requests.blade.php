@extends('layouts.app')

@section('title', 'Demandes de changement d’appareil')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-kicker">Sécurité</div>
            <h1 class="mb-1">Demandes de changement d’appareil</h1>
            <p class="text-muted mb-0">Vérifiez les demandes avant d’autoriser un nouvel appareil.</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Employé</th><th>Ancien appareil</th><th>Nouvel appareil</th><th>Informations du nouvel appareil</th><th>Demandé le</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($requests as $change)
                        <tr>
                            <td>{{ $change->employee?->fullName() ?? $change->employee?->user?->name }}<br><small class="text-muted">Compte #{{ $change->employee?->user_id }} · {{ $change->employee?->user?->email }}</small></td>
                            <td><code>{{ $change->old_device_id ?? 'Aucun' }}</code></td>
                            <td><code>{{ $change->new_device_id }}</code></td>
                            <td><small>{{ $change->new_device_user_agent ?: 'Navigateur inconnu' }}</small><br><small class="text-muted">IP : {{ $change->new_device_ip ?: 'inconnue' }}</small></td>
                            <td>{{ $change->created_at->format('d/m/Y H:i') }}</td>
                            <td><span class="badge text-bg-warning">En attente de validation</span></td>
                            <td class="text-end">
                                <form class="d-inline" method="POST" action="{{ route('devices.requests.approve', $change) }}">@csrf @method('PATCH')<button class="btn btn-success btn-sm">Valider</button></form>
                                <form class="d-inline" method="POST" action="{{ route('devices.requests.reject', $change) }}">@csrf @method('PATCH')<button class="btn btn-outline-danger btn-sm">Rejeter</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune demande en attente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $requests->links() }}</div>
@endsection
