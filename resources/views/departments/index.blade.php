@extends('layouts.app')

@section('title', 'Services')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-kicker">Organisation</div>
            <h1 class="mb-1">Services</h1>
            <p class="text-muted mb-0">Structurez les équipes et leurs rattachements.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i> Nouveau service
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Employés</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td>{{ $department->name }}</td>
                            <td>{{ $department->employees_count }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editModal{{ $department->id }}">
                                    <i class="bi bi-pencil-square me-1"></i> Modifier
                                </button>
                                <form action="{{ route('departments.destroy', $department) }}" method="POST"
                                    class="d-inline" onsubmit="return confirm('Supprimer ce service ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>
                                        Supprimer</button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal{{ $department->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="POST" action="{{ route('departments.update', $department) }}"
                                    class="modal-content">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Modifier le service</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="text" name="name" class="form-control"
                                            value="{{ $department->name }}" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button class="btn btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Aucun service.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $departments->links() }}</div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('departments.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" name="name" class="form-control" placeholder="Nom du service" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
@endsection
