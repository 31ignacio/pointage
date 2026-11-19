<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceScheduleSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MonthlyAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $report = $this->report($request);

        return view('attendance.monthly', [
            ...$report,
            'sites' => Site::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function updateSchedule(Request $request)
    {
        $validated = $request->validate([
            'arrival_time' => ['required', 'date_format:H:i'],
            'departure_time' => ['required', 'date_format:H:i'],
        ], [
            'arrival_time.required' => 'Veuillez indiquer l’heure d’arrivée de référence.',
            'arrival_time.date_format' => 'L’heure d’arrivée doit respecter le format heures et minutes.',
            'departure_time.required' => 'Veuillez indiquer l’heure de départ de référence.',
            'departure_time.date_format' => 'L’heure de départ doit respecter le format heures et minutes.',
        ]);
        $settings = AttendanceScheduleSetting::firstOrCreate([], [
            'arrival_time' => '08:00:00', 'departure_time' => '19:30:00',
        ]);
        $settings->update([
            'arrival_time' => $validated['arrival_time'],
            'departure_time' => $validated['departure_time'],
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('monthly-attendance.index', $request->only(['month', 'year', 'site_id', 'department_id', 'employee']))
            ->with('status', 'Les horaires de référence ont été enregistrés.');
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $report = $this->report($request);
        $filename = 'suivi-mensuel-' . $report['start']->format('Y-m');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.monthly-attendance', $report)
                ->setPaper('a3', 'landscape')->download($filename . '.pdf');
        }

        $path = $this->createXlsx($report);
        return response()->download($path, $filename . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** Charge une fois les pointages acceptés et sépare les pauses des horaires travaillés. */
    private function report(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'site_id' => ['sometimes', 'nullable', 'integer', 'exists:sites,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'employee' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $siteId = $validated['site_id'] ?? null;
        $departmentId = $validated['department_id'] ?? null;
        $employeeSearch = trim($validated['employee'] ?? '');
        $settings = AttendanceScheduleSetting::firstOrCreate([], [
            'arrival_time' => '08:00:00', 'departure_time' => '19:30:00',
        ]);
        $arrivalThreshold = Carbon::parse($settings->arrival_time)->hour * 60 + Carbon::parse($settings->arrival_time)->minute;
        $departureThreshold = Carbon::parse($settings->departure_time)->hour * 60 + Carbon::parse($settings->departure_time)->minute;

        $employees = Employee::query()->with(['site', 'department'])
            ->where(function (Builder $query) use ($start, $end) {
                $query->where('status', 'active')->orWhereHas('attendanceRecords', fn (Builder $records) =>
                    $records->where('status', 'accepted')->whereBetween('recorded_at', [$start, $end]));
            })
            ->when($siteId, fn (Builder $q) => $q->where('site_id', $siteId))
            ->when($departmentId, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->when($employeeSearch !== '', fn (Builder $q) => $q->where(function (Builder $name) use ($employeeSearch) {
                $name->where('first_name', 'like', "%{$employeeSearch}%")
                    ->orWhere('last_name', 'like', "%{$employeeSearch}%")
                    ->orWhere('matricule', 'like', "%{$employeeSearch}%");
            }))
            ->orderBy('site_id')->orderBy('department_id')->orderBy('last_name')->orderBy('first_name')->get();

        $employeeIds = $employees->modelKeys();
        $records = $employeeIds ? AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)->where('status', 'accepted')
            ->whereBetween('recorded_at', [$start, $end])->orderBy('recorded_at')->get()
            ->groupBy(fn (AttendanceRecord $record) => $record->employee_id . '|' . $record->recorded_at->format('Y-m-d')) : collect();

        $daysCount = $start->daysInMonth;
        $rows = $employees->map(function (Employee $employee) use ($records, $daysCount, $start, $arrivalThreshold, $departureThreshold) {
            $days = [];
            $lateTotal = 0;
            $overtimeTotal = 0;
            for ($day = 1; $day <= $daysCount; $day++) {
                $date = $start->copy()->day($day)->toDateString();
                $dayRecords = $records->get($employee->id . '|' . $date, collect())->values();
                $arrival = $dayRecords->firstWhere('type', 'arrival');
                $departure = $dayRecords->where('type', 'departure')->last();
                $breaks = [];
                for ($i = 1; $i < $dayRecords->count() - 1; $i++) {
                    $pauseOut = $dayRecords[$i];
                    $pauseIn = $dayRecords[$i + 1];
                    if ($pauseOut->type === 'departure' && $pauseIn->type === 'arrival') {
                        $breaks[] = [
                            'departure' => $pauseOut->recorded_at->format('H:i'),
                            'arrival' => $pauseIn->recorded_at->format('H:i'),
                        ];
                        $i++;
                    }
                }
                $late = $arrival ? max(0, $arrival->recorded_at->hour * 60 + $arrival->recorded_at->minute - $arrivalThreshold) : 0;
                $overtime = $departure ? max(0, $departure->recorded_at->hour * 60 + $departure->recorded_at->minute - $departureThreshold) : 0;
                $lateTotal += $late;
                $overtimeTotal += $overtime;
                $days[$day] = [
                    'arrival' => $arrival?->recorded_at->format('H:i'),
                    'departure' => $departure?->recorded_at->format('H:i'),
                    'breaks' => $breaks,
                    'late' => $late,
                    'overtime' => $overtime,
                    'status' => ! $arrival && ! $departure ? 'none' : ((! $arrival || ! $departure) ? 'incomplete' : 'complete'),
                ];
            }
            return [
                'employee' => $employee, 'days' => $days,
                'late_total' => $lateTotal, 'overtime_total' => $overtimeTotal,
            ];
        });

        $siteName = $siteId ? Site::find($siteId)?->name : null;
        $departmentName = $departmentId ? Department::find($departmentId)?->name : null;
        $filtersLabel = collect([$siteName, $departmentName, $employeeSearch ?: null])->filter()->implode(' · ');

        return [
            'rows' => $rows, 'start' => $start, 'end' => $end, 'daysCount' => $daysCount,
            'month' => $month, 'year' => $year, 'siteId' => $siteId, 'departmentId' => $departmentId,
            'employeeSearch' => $employeeSearch, 'siteName' => $siteName, 'departmentName' => $departmentName,
            'filtersLabel' => $filtersLabel ?: 'Aucun filtre organisationnel',
            'arrivalTime' => substr($settings->arrival_time, 0, 5), 'departureTime' => substr($settings->departure_time, 0, 5),
            'lateGrandTotal' => $rows->sum('late_total'), 'overtimeGrandTotal' => $rows->sum('overtime_total'),
            'formatMinutes' => static function (int $minutes): string {
                $hours = intdiv($minutes, 60);
                $remainingMinutes = $minutes % 60;
                if ($hours === 0) return $minutes . ' min';
                if ($remainingMinutes === 0) return $hours . ' h';
                return $hours . ' h ' . $remainingMinutes . ' min';
            },
        ];
    }

    private function createXlsx(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'monthly-attendance-');
        if ($path === false) throw new \RuntimeException('Impossible de créer le fichier Excel.');
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($path); throw new \RuntimeException('Impossible de préparer le fichier Excel.');
        }
        $headers = ['N°', 'Employé', 'Siège', 'Département'];
        foreach (range(1, $report['daysCount']) as $day) $headers[] = (string) $day;
        $headers[] = 'Total des retards'; $headers[] = 'Total des heures supplémentaires';
        $rows = [$headers];
        foreach ($report['rows'] as $index => $row) {
            $values = [$index + 1, $row['employee']->fullName(), $row['employee']->site?->name ?? '', $row['employee']->department?->name ?? ''];
            foreach ($row['days'] as $day) {
                if ($day['status'] === 'none') {
                    $values[] = 'Aucun pointage';
                    continue;
                }
                $pauseText = collect($day['breaks'])->map(fn ($pause) => 'Pause ' . $pause['departure'] . '–' . $pause['arrival'])->implode(' / ');
                $details = array_filter([
                    $day['arrival'] ? 'Arrivée ' . $day['arrival'] : null,
                    $pauseText ?: null,
                    $day['departure'] ? 'Départ ' . $day['departure'] : null,
                    $day['status'] === 'incomplete' ? 'Pointage incomplet' : null,
                    'Retard : ' . $report['formatMinutes']($day['late']),
                    'Heures supplémentaires : ' . $report['formatMinutes']($day['overtime']),
                ]);
                $values[] = implode(' · ', $details);
            }
            $values[] = $row['late_total']; $values[] = $row['overtime_total']; $rows[] = $values;
        }
        $rows[] = array_merge(['', 'TOTAL GÉNÉRAL', '', ''], array_fill(0, $report['daysCount'], ''), [$report['lateGrandTotal'], $report['overtimeGrandTotal']]);
        $colName = static function (int $n): string { $name = ''; while ($n > 0) { $r = ($n - 1) % 26; $name = chr(65 + $r) . $name; $n = intdiv($n - 1, 26); } return $name; };
        $xmlEscape = static fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sheetRows = '';
        foreach ($rows as $ri => $values) {
            $r = $ri + 1; $sheetRows .= '<row r="' . $r . '">';
            foreach ($values as $ci => $value) {
                $cell = $colName($ci + 1) . $r;
                $durationColumn = $ci >= count($headers) - 2;
                $style = $r === 1 ? ' s="1"' : ($durationColumn && $r > 1 ? ' s="3"' : ($r === count($rows) ? ' s="2"' : ''));
                if ($durationColumn && $r > 1 && is_numeric($value)) {
                    $sheetRows .= '<c r="' . $cell . '"' . $style . '><v>' . ((float) $value / 1440) . '</v></c>';
                } elseif (is_numeric($value) && $value !== '') $sheetRows .= '<c r="' . $cell . '"' . $style . '><v>' . $value . '</v></c>';
                else $sheetRows .= '<c r="' . $cell . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $xmlEscape($value) . '</t></is></c>';
            }
            $sheetRows .= '</row>';
        }
        $lastCol = $colName(count($headers)); $lastRow = count($rows);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane xSplit="4" ySplit="1" topLeftCell="E2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><sheetData>' . $sheetRows . '</sheetData><autoFilter ref="A1:' . $lastCol . ($lastRow - 1) . '"/><pageSetup orientation="landscape" fitToWidth="1"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5"/></worksheet>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Suivi mensuel" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="[&gt;=0.04166666][h]&quot; h &quot;mm&quot; min&quot;;[m]&quot; min&quot;;0&quot; min&quot;"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF243B63"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet); $zip->close();
        return $path;
    }
}
