<!doctype html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Rapport des arrivées avant l'heure</title>
    <style>
        @page { margin: 28px 32px; }
        body { color: #243047; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { margin: 0 0 7px; color: #182849; font-size: 19px; }
        .meta { margin-bottom: 17px; color: #53627b; line-height: 1.7; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 8px 6px; background: #263b66; color: #fff; font-size: 9px; text-align: left; }
        td { padding: 7px 6px; border-bottom: 1px solid #e3e8f0; }
        tr:nth-child(even) td { background: #f6f8fb; }
        .empty { padding: 18px; color: #71809a; text-align: center; }
    </style>
</head>
<body>
    <h1>Arrivées avant l'heure</h1>
    <div class="meta">
        Période : {{ $periodLabel }} &nbsp; | &nbsp; Heure limite : avant {{ $beforeTime }}<br>
        @if($department) Service : {{ $department }} &nbsp; | &nbsp; @endif
        @if($employee) Employé : {{ $employee }} &nbsp; | &nbsp; @endif
        {{ $records->count() }} résultat(s) — généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
    <table>
        <thead><tr><th>Employé</th><th>Matricule</th><th>Service</th><th>Date</th><th>Heure d'arrivée</th><th>Site</th></tr></thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ $record->employee?->fullName() ?? 'Employé supprimé' }}</td>
                    <td>{{ $record->employee?->matricule ?? '—' }}</td>
                    <td>{{ $record->employee?->department?->name ?? '—' }}</td>
                    <td>{{ $record->recorded_at->format('d/m/Y') }}</td>
                    <td>{{ $record->recorded_at->format('H:i') }}</td>
                    <td>{{ $record->site?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="6">Aucune arrivée ne correspond à ces critères.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
