@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-kicker">Vue d'ensemble</div>
            <h1 class="mb-1">Tableau de bord</h1>
            <p class="text-muted mb-0">Suivez l'activité du personnel au {{ now()->translatedFormat('d F Y') }}.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card stat-card text-center"><div class="text-muted small">Total salariés</div><div class="stat-value">{{ $totalEmployees }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card text-center"><div class="text-muted small">Présents</div><div class="stat-value text-success">{{ $presents }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card text-center"><div class="text-muted small">Sortis</div><div class="stat-value text-warning">{{ $sortis }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card text-center"><div class="text-muted small">Absents</div><div class="stat-value text-danger">{{ $absents }}</div></div></div>
    </div>

    <section class="card mb-4 early-report">
        <div class="card-header">
            <button class="early-report-toggle" type="button" id="early-report-toggle" aria-expanded="false" aria-controls="early-report-content">
                <span><i class="bi bi-alarm me-2 text-primary"></i>Arrivées avant l'heure</span>
                <span class="early-report-meta">
                    <span class="badge text-bg-light">{{ $earlyArrivals->total() }} résultat(s)</span>
                    <i class="bi bi-chevron-down early-report-chevron early-report-chevron-down" aria-hidden="true"></i>
                    <i class="bi bi-chevron-up early-report-chevron early-report-chevron-up" aria-hidden="true"></i>
                </span>
            </button>
        </div>
        <div id="early-report-content" hidden>
        <div class="card-body">
            <p class="text-muted small mb-3">Recherchez les arrivées avant l'heure choisie, par période, service ou employé.</p>
            <form method="GET" action="{{ route('dashboard') }}" class="row g-3 align-items-end" id="early-arrivals-filter">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="period">Période</label>
                    <select class="form-select" id="period" name="period">
                        <option value="weekly" @selected($period === 'weekly')>Cette semaine</option>
                        <option value="monthly" @selected($period === 'monthly')>Ce mois-ci</option>
                        <option value="quarterly" @selected($period === 'quarterly')>Ce trimestre</option>
                        <option value="custom" @selected($period === 'custom')>Période personnalisée</option>
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-lg-2 custom-date-field" @if($period !== 'custom') hidden @endif>
                    <label class="form-label" for="start_date">Date de début</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="{{ old('start_date', $period === 'custom' ? $startDate->toDateString() : '') }}" @if($period === 'custom') required @endif>
                </div>
                <div class="col-6 col-sm-3 col-lg-2 custom-date-field" @if($period !== 'custom') hidden @endif>
                    <label class="form-label" for="end_date">Date de fin</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="{{ old('end_date', $period === 'custom' ? $endDate->toDateString() : '') }}" min="{{ old('start_date', $period === 'custom' ? $startDate->toDateString() : '') }}" @if($period === 'custom') required @endif>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="before_time">Arrivées avant</label>
                    <select class="form-select" id="before_time" name="before_time">
                        @foreach(range(5, 12) as $hour)
                            <option value="{{ sprintf('%02d:00', $hour) }}" @selected($beforeTime === sprintf('%02d:00', $hour))>{{ sprintf('%02dh00', $hour) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="department_id">Service</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">Tous les services</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="employee_id">Employé</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <option value="">Tous les employés</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" data-department-id="{{ $employee->department_id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->fullName() }} — {{ $employee->matricule }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                    <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                    <button class="btn btn-outline-secondary" type="submit" formaction="{{ route('dashboard.export', ['format' => 'pdf']) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Exporter PDF</button>
                    <button class="btn btn-outline-secondary" type="submit" formaction="{{ route('dashboard.export', ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exporter Excel</button>
                </div>
            </form>
            @if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif
            <div class="report-summary mt-3"><i class="bi bi-calendar3 me-1"></i>{{ $periodLabel }} <span class="mx-1">·</span> arrivées avant {{ $beforeTime }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Employé</th><th>Matricule</th><th>Service</th><th>Date</th><th>Heure d'arrivée</th><th>Site</th></tr></thead>
                <tbody>
                    @forelse($earlyArrivals as $record)
                        <tr>
                            <td class="fw-bold">{{ $record->employee?->fullName() ?? 'Employé supprimé' }}</td>
                            <td>{{ $record->employee?->matricule ?? '—' }}</td>
                            <td>{{ $record->employee?->department?->name ?? '—' }}</td>
                            <td>{{ $record->recorded_at->format('d/m/Y') }}</td>
                            <td><span class="badge bg-success-subtle text-success-emphasis">{{ $record->recorded_at->format('H:i') }}</span></td>
                            <td>{{ $record->site?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucune arrivée avant {{ $beforeTime }} pour {{ $periodLabel }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($earlyArrivals->hasPages())<div class="card-body py-3">{{ $earlyArrivals->links() }}</div>@endif
        </div>
    </section>

    <div class="card">
        <div class="card-header bg-white">Derniers pointages du jour</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Employé</th><th>Heure</th><th>Type</th><th>Site</th></tr></thead>
                <tbody>
                    @forelse($recentRecords as $record)
                        <tr>
                            <td>{{ $record->employee?->fullName() }}</td>
                            <td>{{ $record->recorded_at->format('H:i') }}</td>
                            <td>@if($record->type === 'arrival')<span class="badge bg-success">Arrivée</span>@else<span class="badge bg-warning text-dark">Sortie</span>@endif</td>
                            <td>{{ $record->site?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucun pointage aujourd'hui.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (() => {
            const period = document.getElementById('period');
            const dateFields = document.querySelectorAll('.custom-date-field');
            const start = document.getElementById('start_date');
            const end = document.getElementById('end_date');
            const department = document.getElementById('department_id');
            const employee = document.getElementById('employee_id');
            const earlyReportToggle = document.getElementById('early-report-toggle');
            const earlyReportContent = document.getElementById('early-report-content');
            const earlyReportStorageKey = 'dashboard-early-report-expanded';
            const updatePeriodFields = () => {
                const custom = period.value === 'custom';
                dateFields.forEach((field) => { field.hidden = !custom; });
                start.required = custom;
                end.required = custom;
                end.min = custom ? start.value : '';
            };
            const filterEmployees = () => {
                const departmentId = department.value;
                Array.from(employee.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = Boolean(departmentId && option.dataset.departmentId !== departmentId);
                    if (option.hidden && option.selected) employee.value = '';
                });
            };
            period.addEventListener('change', updatePeriodFields);
            start.addEventListener('change', updatePeriodFields);
            department.addEventListener('change', filterEmployees);
            const setEarlyReportExpanded = (expanded) => {
                earlyReportToggle.setAttribute('aria-expanded', String(expanded));
                earlyReportContent.hidden = !expanded;
                sessionStorage.setItem(earlyReportStorageKey, String(expanded));
            };
            const savedEarlyReportState = sessionStorage.getItem(earlyReportStorageKey);
            if (savedEarlyReportState !== null) setEarlyReportExpanded(savedEarlyReportState === 'true');
            earlyReportToggle.addEventListener('click', () => {
                const expanded = earlyReportToggle.getAttribute('aria-expanded') === 'true';
                setEarlyReportExpanded(!expanded);
            });
            filterEmployees();
        })();
    </script>
@endsection
