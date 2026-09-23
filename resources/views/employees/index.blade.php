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
                            <td class="text-end">
                                <a class="btn btn-outline-primary"
                                    href="{{ route('employees.history', $employee) }}" title="Historique">
                                    <i class="bi bi-clock-history me-1"></i>
                                </a>
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editModal{{ $employee->id }}" title="Modifier">
                                    <i class="bi bi-pencil-square me-1"></i>
                                </button>
                                <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Supprimer cet employé ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash3 me-1"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal{{ $employee->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <form method="POST" action="{{ route('employees.update', $employee) }}"
                                    class="modal-content">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Modifier {{ $employee->fullName() }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                        <button class="btn btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Aucun employé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('employees.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouvel employé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <button class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
@endsection
