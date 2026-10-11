@extends('layouts.app')

@section('title', 'Employés')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-kicker">Équipe</div>
            <h1 class="mb-1">Employés</h1>
            <p class="text-muted mb-0">Gérez les comptes, rôles et affectations de votre équipe.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i> Nouvel employé
        </button>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                placeholder="Rechercher (nom, matricule)">
        </div>
        <div class="col-auto">
            <select name="department_id" class="form-select">
                <option value="">Tous les services</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary">Filtrer</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>Service</th>
                        <th>Site</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Sécurité du pointage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td>{{ $employee->matricule }}</td>
                            <td>{{ $employee->fullName() }}</td>
                            <td>{{ $employee->department?->name }}</td>
                            <td>{{ $employee->site?->name }}</td>
                            <td>{{ $employee->user?->role }}</td>
                            <td>
                                @if ($employee->status === 'active')
                                    <span class="badge bg-success">Actif</span>
                                @else
                                    <span class="badge bg-secondary">Inactif</span>
                                @endif
                            </td>
                            <td>
                                @if ($employee->user?->role === 'employe')
                                    @if ($employee->user->passkeys->isNotEmpty())
                                        <span class="badge text-bg-success">Passkey active</span>
                                    @else
                                        <span class="badge text-bg-warning">Passkey absente</span>
                                    @endif
                                    @if ($employee->device_fallback_enabled)
                                        <div><span class="badge text-bg-info mt-1">Secours appareil activé</span></div>
                                    @endif
                                @else
                                    <span class="text-muted small">Non requis</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary"
                                    href="{{ route('employees.history', $employee) }}" title="Historique">
                                    <i class="bi bi-clock-history me-1"></i>
                                </a>
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editModal{{ $employee->id }}" title="Modifier">
                                    <i class="bi bi-pencil-square me-1"></i>
                                </button>
                                @if ($employee->user?->role === 'employe')
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generatePasskeyModal" data-activation-url="{{ route('employees.passkey.activation-code', $employee) }}" data-employee-name="{{ $employee->fullName() }}" title="Réinitialiser la passkey et générer un code"><i class="bi bi-fingerprint"></i></button>
                                    <form class="d-inline" method="POST" action="{{ route('employees.device-fallback', $employee) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="enabled" value="{{ $employee->device_fallback_enabled ? '0' : '1' }}">
                                        <button class="btn {{ $employee->device_fallback_enabled ? 'btn-info' : 'btn-outline-info' }}" title="{{ $employee->device_fallback_enabled ? 'Désactiver le secours par appareil' : 'Activer le secours par appareil' }}"><i class="bi bi-phone"></i></button>
                                    </form>
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetDeviceModal" data-reset-url="{{ route('employees.device.reset', $employee) }}" data-employee-name="{{ $employee->fullName() }}" title="Réinitialiser l’appareil de confiance"><i class="bi bi-phone-flip"></i></button>
                                @endif
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal" data-employee-name="{{ $employee->fullName() }}"
                                    data-delete-url="{{ route('employees.destroy', $employee) }}" title="Supprimer">
                                    <i class="bi bi-trash3 me-1"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucun employé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>

    {{-- ===== Modals de modification (un par employé, mais en dehors du tableau) ===== --}}
    @foreach ($employees as $employee)
        <div class="modal fade" id="editModal{{ $employee->id }}" tabindex="-1"
            aria-labelledby="editModalLabel{{ $employee->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form method="POST" action="{{ route('employees.update', $employee) }}" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel{{ $employee->id }}">Modifier {{ $employee->fullName() }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('employees._fields', [
                            'employee' => $employee,
                            'departments' => $departments,
                            'sites' => $sites,
                            'editing' => true,
                        ])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    {{-- ===== Modal de création ===== --}}
    <div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('employees.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createModalLabel">Nouvel employé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('employees._fields', [
                        'employee' => null,
                        'departments' => $departments,
                        'sites' => $sites,
                        'editing' => false,
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Modal de suppression (unique, réutilisé pour chaque employé) ===== --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Êtes-vous sûr de vouloir supprimer <strong id="deleteEmployeeName"></strong> ?
                        Cette action est irréversible.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form id="deleteForm" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash3 me-1"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetDeviceModal" tabindex="-1" aria-labelledby="resetDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="resetDeviceModalLabel">Réinitialiser l’appareil</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                <div class="modal-body">Réinitialiser l’appareil de confiance de <strong id="resetEmployeeName"></strong> ? Le prochain pointage accepté enregistrera le nouvel appareil.</div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><form id="resetDeviceForm" method="POST" action="">@csrf @method('DELETE')<button class="btn btn-warning">Réinitialiser</button></form></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="generatePasskeyModal" tabindex="-1" aria-labelledby="generatePasskeyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="generatePasskeyModalLabel">Réinitialiser la passkey</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                <div class="modal-body">Cette action révoque les passkeys existantes de <strong id="passkeyEmployeeName"></strong> et génère un nouveau code, valable 24 heures. Le code ne sera affiché qu’une seule fois.</div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><form id="generatePasskeyForm" method="POST" action="">@csrf<button class="btn btn-primary">Réinitialiser et générer le code</button></form></div>
            </div>
        </div>
    </div>

    @if (session('passkey_activation_code'))
        <div class="modal fade" id="activationCodeModal" tabindex="-1" aria-labelledby="activationCodeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="activationCodeModalLabel">Code d’activation — {{ session('passkey_activation_employee') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body"><p>Communiquez ce code de manière sécurisée à l’employé. Il expire dans 24 heures et ne peut servir qu’une seule fois.</p><div class="input-group"><input id="activationCodeValue" class="form-control font-monospace fs-5" readonly value="{{ session('passkey_activation_code') }}"><button class="btn btn-outline-primary" type="button" id="copyActivationCode"><i class="bi bi-clipboard"></i> Copier</button></div></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Terminé</button></div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('activationCodeModal')).show();
                document.getElementById('copyActivationCode').addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(document.getElementById('activationCodeValue').value);
                        window.showToast('Code copié.', 'success');
                    } catch (_) {
                        document.getElementById('activationCodeValue').select();
                        window.showToast('Sélectionnez le code puis copiez-le.', 'info');
                    }
                });
            });
        </script>
    @endif

    <script>
        // Remplit le modal de suppression avec les infos de l'employé cliqué
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var employeeName = button.getAttribute('data-employee-name');
            var deleteUrl = button.getAttribute('data-delete-url');

            document.getElementById('deleteEmployeeName').textContent = employeeName;
            document.getElementById('deleteForm').setAttribute('action', deleteUrl);
        });
        document.getElementById('resetDeviceModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('resetEmployeeName').textContent = button.dataset.employeeName;
            document.getElementById('resetDeviceForm').action = button.dataset.resetUrl;
        });
        document.getElementById('generatePasskeyModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('passkeyEmployeeName').textContent = button.dataset.employeeName;
            document.getElementById('generatePasskeyForm').action = button.dataset.activationUrl;
        });
    </script>
@endsection
