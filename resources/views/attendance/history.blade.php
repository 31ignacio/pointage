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

<form method="GET" action="{{ $employee && auth()->user()->isAdmin() ? route('employees.history', $employee) : route('attendance.history') }}" class="card p-3 mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="date_from">Du</label><input class="form-control" type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="date_to">Au</label><input class="form-control" type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="type">Type de pointage</label><select class="form-select" id="type" name="type"><option value="">Tous</option><option value="arrival" @selected(request('type') === 'arrival')>Arrivée</option><option value="departure" @selected(request('type') === 'departure')>Sortie</option></select></div>
        <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrer</button><a class="btn btn-outline-secondary" href="{{ $employee && auth()->user()->isAdmin() ? route('employees.history', $employee) : route('attendance.history') }}">Effacer</a></div>
    </div>
</form>

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
