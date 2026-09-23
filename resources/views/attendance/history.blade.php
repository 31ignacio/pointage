@extends('layouts.app')

@section('title', 'Mon historique')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">
        @if($employee && auth()->user()->isAdmin())
            Historique de pointages — {{ $employee->fullName() }}
        @else
            Mon historique de pointages
        @endif
    </h1>
    @if($employee && auth()->user()->isAdmin())
        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm">Retour aux employés</a>
    @endif
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Type</th>
                    <th>Site</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr>
                        <td>{{ $record->recorded_at->format('d/m/Y') }}</td>
                        <td>{{ $record->recorded_at->format('H:i') }}</td>
                        <td>
                            @if($record->type === 'arrival')
                                <span class="badge bg-success">Arrivée</span>
                            @else
                                <span class="badge bg-warning text-dark">Sortie</span>
                            @endif
                        </td>
                        <td>{{ $record->site?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Aucun pointage enregistré.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $records instanceof \Illuminate\Pagination\LengthAwarePaginator ? $records->links() : '' }}
</div>
@endsection
