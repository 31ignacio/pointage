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
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal" data-site-name="{{ $site->name }}"
                                    data-delete-url="{{ route('sites.destroy', $site) }}">
                                    <i class="bi bi-trash3 me-1"></i> Supprimer
                                </button>
                            </td>
                        </tr>
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

    {{-- ===== Modals de modification (un par site, en dehors du tableau) ===== --}}
    @foreach ($sites as $site)
        <div class="modal fade" id="editModal{{ $site->id }}" tabindex="-1"
            aria-labelledby="editModalLabel{{ $site->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form method="POST" action="{{ route('sites.update', $site) }}" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel{{ $site->id }}">Modifier {{ $site->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('sites._fields', ['site' => $site])
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
            <form method="POST" action="{{ route('sites.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createModalLabel">Nouveau site</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('sites._fields', ['site' => null])
                    <div class="form-text mt-2">
                        Astuce : ouvrez Google Maps sur le lieu, faites un clic droit et copiez les
                        coordonnées (latitude, longitude).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Modal de suppression (unique, réutilisé pour chaque site) ===== --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Êtes-vous sûr de vouloir supprimer le site <strong id="deleteSiteName"></strong> ?
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

    <script>
        // Remplit le modal de suppression avec les infos du site cliqué
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var siteName = button.getAttribute('data-site-name');
            var deleteUrl = button.getAttribute('data-delete-url');

            document.getElementById('deleteSiteName').textContent = siteName;
            document.getElementById('deleteForm').setAttribute('action', deleteUrl);
        });
    </script>
@endsection