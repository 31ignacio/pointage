@extends('layouts.app')

@section('title', 'Sites')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-kicker">Géolocalisation</div>
            <h1 class="mb-1">Sites</h1>
            <p class="text-muted mb-0">Définissez les zones autorisées pour le pointage.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i> Nouveau site
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Adresse</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Rayon (m)</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sites as $site)
                        <tr>
                            <td>{{ $site->name }}</td>
                            <td>{{ $site->address }}</td>
                            <td>{{ $site->latitude }}</td>
                            <td>{{ $site->longitude }}</td>
                            <td>{{ $site->radius_m }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editModal{{ $site->id }}">
                                    <i class="bi bi-pencil-square me-1"></i> Modifier
                                </button>
                                <form action="{{ route('sites.destroy', $site) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Supprimer ce site ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>
                                        Supprimer</button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal{{ $site->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <form method="POST" action="{{ route('sites.update', $site) }}" class="modal-content">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Modifier le site</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        @include('sites._fields', ['site' => $site])
                                    </div>
                                    <div class="modal-footer">
                                        <button class="btn btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Aucun site.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sites->links() }}</div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('sites.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau site</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('sites._fields', ['site' => null])
                    <div class="form-text mt-2">
                        Astuce : ouvrez Google Maps sur le lieu, faites un clic droit et copiez les
                        coordonnées (latitude, longitude).
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
@endsection
