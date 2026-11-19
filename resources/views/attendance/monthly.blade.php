@extends('layouts.app')

@section('title', 'Suivi mensuel')

@push('styles')
<style>
.monthly-scroll{max-height:72vh;overflow:auto;border:1px solid #e5eaf2;border-radius:1rem}.monthly-table{min-width:max-content;font-size:.75rem}.monthly-table th,.monthly-table td{padding:.45rem .5rem!important;text-align:center;border-color:#e9edf4;white-space:nowrap}.monthly-table thead th{position:sticky;top:0;z-index:4;background:#f6f8fc;color:#50607b;letter-spacing:0;text-transform:none}.monthly-table .col-number{position:sticky;left:0;z-index:3;background:#fff;min-width:48px}.monthly-table .col-name{position:sticky;left:48px;z-index:3;background:#fff;min-width:190px;text-align:left}.monthly-table thead .col-number,.monthly-table thead .col-name{z-index:6;background:#f6f8fc}.monthly-table .group-row td{position:sticky;left:0;z-index:2;background:#edf2fc;text-align:left;font-weight:800;color:#364b78}.day-cell{min-width:104px}.day-cell .event{display:block;color:#58667c;font-size:.64rem}.day-cell .late{color:#a64d18;font-weight:800}.day-cell .extra{color:#177a68;font-weight:800}.day-cell .missing{color:#9aa5b5}.monthly-total{position:sticky;right:0;z-index:3;background:#fff;min-width:112px;font-weight:800}.monthly-table thead .monthly-total{z-index:6;background:#f6f8fc}.monthly-total-extra{right:112px}.monthly-table tfoot td{position:sticky;bottom:0;z-index:4;background:#f6f8fc;font-weight:800}.monthly-empty{padding:3rem 1rem;text-align:center;color:#77849a}.filter-card{padding:1rem 1.15rem}.monthly-actions{display:flex;gap:.5rem;flex-wrap:wrap}@media(max-width:768px){.monthly-scroll{max-height:65vh}.monthly-table .col-name{min-width:145px}}
</style>
@endpush

@section('content')
<div class="page-heading">
    <div>
        <div class="page-kicker">Administration · Ressources humaines</div>
        <h1>Suivi mensuel des retards et des heures supplémentaires</h1>
        <p class="text-secondary mb-0">{{ $start->translatedFormat('F Y') }} · Arrivée attendue à {{ $arrivalTime }} · Départ attendu à {{ $departureTime }}</p>
    </div>
    <div class="monthly-actions">
        <button class="btn btn-outline-secondary" type="submit" form="monthly-filter" formaction="{{ route('monthly-attendance.export', ['format' => 'pdf']) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Exporter en PDF</button>
        <button class="btn btn-outline-secondary" type="submit" form="monthly-filter" formaction="{{ route('monthly-attendance.export', ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exporter vers Excel</button>
    </div>
</div>

<section class="card filter-card mb-3">
    <form method="GET" action="{{ route('monthly-attendance.index') }}" id="monthly-filter" class="row g-3 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label" for="month">Mois</label><select class="form-select" name="month" id="month">@foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::create()->month($m)->translatedFormat('F') }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="year">Année</label><input class="form-control" type="number" name="year" id="year" min="2000" max="2100" value="{{ $year }}"></div>
        <div class="col-md-2"><label class="form-label" for="site_id">Siège</label><select class="form-select" name="site_id" id="site_id"><option value="">Tous les sièges</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) $siteId === (string) $site->id)>{{ $site->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="department_id">Département</label><select class="form-select" name="department_id" id="department_id"><option value="">Tous les départements</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) $departmentId === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="employee">Employé ou matricule</label><input class="form-control" name="employee" id="employee" value="{{ $employeeSearch }}" placeholder="Rechercher"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Appliquer les filtres</button></div>
    </form>
</section>

<section class="card filter-card mb-3">
    <form method="POST" action="{{ route('monthly-attendance.schedule.update') }}" class="row g-3 align-items-end">
        @csrf @method('PUT')
        <input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="year" value="{{ $year }}">
        @if($siteId)<input type="hidden" name="site_id" value="{{ $siteId }}">@endif
        @if($departmentId)<input type="hidden" name="department_id" value="{{ $departmentId }}">@endif
        @if($employeeSearch)<input type="hidden" name="employee" value="{{ $employeeSearch }}">@endif
        <div class="col-12 col-lg-4"><div class="fw-bold mb-1"><i class="bi bi-clock me-1"></i>Horaires de référence</div><small class="text-secondary">Ces horaires servent au calcul des retards et des heures supplémentaires.</small></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="arrival_time">Heure d’arrivée</label><input class="form-control" type="time" id="arrival_time" name="arrival_time" value="{{ $arrivalTime }}" required></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="departure_time">Heure de départ</label><input class="form-control" type="time" id="departure_time" name="departure_time" value="{{ $departureTime }}" required></div>
        <div class="col-lg-2 d-grid"><button class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Enregistrer les horaires</button></div>
    </form>
</section>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 text-secondary small">
    <span>{{ $rows->count() }} employé(s) · {{ $filtersLabel }}</span>
    <span>Totaux : <strong class="text-warning-emphasis">{{ $formatMinutes($lateGrandTotal) }} de retard</strong> · <strong class="text-success">{{ $formatMinutes($overtimeGrandTotal) }} d’heures supplémentaires</strong></span>
</div>

<section class="card"><div class="monthly-scroll"><table class="table table-bordered monthly-table">
    <thead><tr><th class="col-number">N°</th><th class="col-name">Employé</th>@foreach(range(1, $daysCount) as $day)<th class="day-cell">{{ $day }}<small class="d-block text-secondary">{{ $start->copy()->day($day)->translatedFormat('D') }}</small></th>@endforeach<th class="monthly-total monthly-total-extra">Retards</th><th class="monthly-total">Heures supplémentaires</th></tr></thead>
    <tbody>
        @php($lastGroup = null)
        @forelse($rows as $index => $row)
            @php($group = ($row['employee']->site?->name ?? 'Sans siège').' · '.($row['employee']->department?->name ?? 'Sans département'))
            @if($group !== $lastGroup)<tr class="group-row"><td colspan="{{ $daysCount + 4 }}"><i class="bi bi-building me-1"></i>{{ $group }}</td></tr>@php($lastGroup = $group)@endif
            <tr>
                <td class="col-number">{{ $index + 1 }}</td>
                <td class="col-name"><strong>{{ $row['employee']->fullName() }}</strong><small class="d-block text-secondary">{{ $row['employee']->matricule }}</small></td>
                @foreach($row['days'] as $day)
                    <td class="day-cell">
                        @if($day['arrival'])<span class="event">Arrivée : {{ $day['arrival'] }}</span>@endif
                        @foreach($day['breaks'] as $pause)<span class="event">Pause : {{ $pause['departure'] }} → {{ $pause['arrival'] }}</span>@endforeach
                        @if($day['departure'])<span class="event">Départ : {{ $day['departure'] }}</span>@endif
                        @if($day['status'] === 'none')<span class="missing"> - </span>
                        @else
                            @if($day['status'] === 'incomplete')<span class="event text-secondary">Pointage incomplet</span>@endif
                            @if($day['late'] > 0)<span class="late">Retard : {{ $formatMinutes($day['late']) }}</span>@endif
                            @if($day['overtime'] > 0)<span class="extra">Supplément : {{ $formatMinutes($day['overtime']) }}</span>@endif
                            @if($day['late'] === 0 && $day['overtime'] === 0)<span class="missing">Aucun retard ni heure supplémentaire</span>@endif
                        @endif
                    </td>
                @endforeach
                <td class="monthly-total monthly-total-extra text-warning-emphasis">{{ $formatMinutes($row['late_total']) }}</td>
                <td class="monthly-total text-success">{{ $formatMinutes($row['overtime_total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $daysCount + 4 }}" class="monthly-empty"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>Aucun employé ne correspond à ces filtres.</td></tr>
        @endforelse
    </tbody>
    @if($rows->isNotEmpty())<tfoot><tr><td colspan="{{ $daysCount + 2 }}" class="text-end">Totaux du mois</td><td class="monthly-total monthly-total-extra">{{ $formatMinutes($lateGrandTotal) }}</td><td class="monthly-total">{{ $formatMinutes($overtimeGrandTotal) }}</td></tr></tfoot>@endif
</table></div></section>
<p class="small text-secondary mt-2 mb-0">Les pointages de pause sont affichés, mais ne sont pas comptés comme arrivée tardive ni comme heure supplémentaire. Les retards et dépassements sont calculés uniquement sur la première arrivée et la dernière sortie de la journée.</p>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('monthly-filter');
    if (!form) return;
    form.querySelectorAll('select, input[type="number"]').forEach((field) => field.addEventListener('change', () => form.requestSubmit()));
    const employee = form.querySelector('input[name="employee"]');
    let timer;
    employee?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => form.requestSubmit(), 500);
    });
})();
</script>
@endpush
