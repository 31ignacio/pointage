<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c2940; font-size: 7px; }
        h1 { font-size: 16px; margin: 0 0 5px; }
        p { font-size: 8px; margin: 3px 0; }
        .meta { margin-bottom: 10px; color: #58667c; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: .5px solid #d5dce8; padding: 3px 2px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        th { background: #edf2fa; color: #334361; font-size: 6px; }
        th:first-child, td:first-child { width: 20px; }
        th:nth-child(2), td:nth-child(2) { width: 100px; text-align: left; }
        th:nth-child(3), td:nth-child(3), th:nth-child(4), td:nth-child(4) { width: 30px; }
        .group td { background: #e7edf8; text-align: left; font-weight: bold; }
        .name { font-weight: bold; }
        .muted { color: #6d7b91; font-size: 5px; }
        .late { color: #9a4a17; font-weight: bold; }
        .extra { color: #147661; font-weight: bold; }
        .total { background: #f7f8fb; font-weight: bold; }
        .foot { margin-top: 8px; font-size: 7px; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} — Suivi mensuel des retards et des heures supplémentaires</h1>
    <p class="meta">Période : {{ $start->translatedFormat('F Y') }} · Filtres appliqués : {{ $filtersLabel }} · Horaires de référence : arrivée {{ $arrivalTime }}, départ {{ $departureTime }}</p>
    <table>
        <thead>
            <tr><th>N°</th><th>Employé</th><th>Siège</th><th>Service</th>@foreach(range(1, $daysCount) as $day)<th>{{ $day }}</th>@endforeach<th>Retards</th><th>Heures supplémentaires</th></tr>
        </thead>
        <tbody>
            @php($lastGroup = null)
            @foreach($rows as $index => $row)
                @php($group = ($row['employee']->site?->name ?? 'Sans siège').' · '.($row['employee']->department?->name ?? 'Sans département'))
                @if($group !== $lastGroup)
                    <tr class="group"><td colspan="{{ $daysCount + 6 }}">{{ $group }}</td></tr>
                    @php($lastGroup = $group)
                @endif
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="name">{{ $row['employee']->fullName() }}<br><span class="muted">{{ $row['employee']->matricule }}</span></td>
                    <td>{{ $row['employee']->site?->name ?? '—' }}</td>
                    <td>{{ $row['employee']->department?->name ?? '—' }}</td>
                    @foreach($row['days'] as $day)
                        <td>
                            @if($day['status'] === 'none')
                                -
                            @else
                                @if($day['arrival'])<span class="muted">Arrivée {{ $day['arrival'] }}</span><br>@endif
                                @foreach($day['breaks'] as $pause)<span class="muted">Pause {{ $pause['departure'] }}–{{ $pause['arrival'] }}</span><br>@endforeach
                                @if($day['departure'])<span class="muted">Départ {{ $day['departure'] }}</span><br>@endif
                                @if($day['status'] === 'incomplete')<span class="muted">Pointage incomplet</span><br>@endif
                                @if($day['late'] > 0)<span class="late">Retard : {{ $formatMinutes($day['late']) }}</span><br>@endif
                                @if($day['overtime'] > 0)<span class="extra">Supplément : {{ $formatMinutes($day['overtime']) }}</span>@endif
                                @if($day['late'] === 0 && $day['overtime'] === 0)<span class="muted">Aucun retard ni heure supplémentaire</span>@endif
                            @endif
                        </td>
                    @endforeach
                    <td class="total">{{ $formatMinutes($row['late_total']) }}</td>
                    <td class="total">{{ $formatMinutes($row['overtime_total']) }}</td>
                </tr>
            @endforeach
            @if($rows->isEmpty())
                <tr><td colspan="{{ $daysCount + 6 }}">Aucune donnée disponible pour ces filtres.</td></tr>
            @endif
        </tbody>
        <tfoot><tr class="total"><td colspan="{{ $daysCount + 4 }}" style="text-align:right">Totaux mensuels</td><td>{{ $formatMinutes($lateGrandTotal) }}</td><td>{{ $formatMinutes($overtimeGrandTotal) }}</td></tr></tfoot>
    </table>
    <p class="foot">Les pointages de pause sont indiqués, mais exclus des calculs. Le retard repose sur la première arrivée et les heures supplémentaires sur la dernière sortie.</p>
</body>
</html>
