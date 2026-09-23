<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->reportFilters($request);
        $today = now()->toDateString();

        $totalEmployees = Employee::where("status", "active")->count();
        $todayRecords = AttendanceRecord::with("employee")
            ->where("status", "accepted")
            ->whereDate("recorded_at", $today)
            ->orderBy("recorded_at")
            ->get()
            ->groupBy("employee_id");

        $presents = 0;
        $sortis = 0;
        foreach ($todayRecords as $records) {
            if ($records->last()->type === "arrival") {
                $presents++;
            } else {
                $sortis++;
            }
        }

        $absents = max($totalEmployees - $todayRecords->count(), 0);
        $recentRecords = AttendanceRecord::with(["employee", "site"])
            ->where("status", "accepted")
            ->whereDate("recorded_at", $today)
            ->latest("recorded_at")
            ->limit(20)
            ->get();

        $earlyArrivals = $this->earlyArrivalsQuery($filters)->paginate(30)->withQueryString();
        $departments = Department::orderBy("name")->get();
        $employees = Employee::with("department")->orderBy("last_name")->orderBy("first_name")->get();

        return view("dashboard", [
            "totalEmployees" => $totalEmployees,
            "presents" => $presents,
            "absents" => $absents,
            "sortis" => $sortis,
            "recentRecords" => $recentRecords,
            "departments" => $departments,
            "sites" => Site::all(),
            "employees" => $employees,
            "earlyArrivals" => $earlyArrivals,
            ...$filters,
        ]);
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ["pdf", "xlsx"], true), 404);

        $filters = $this->reportFilters($request);
        $records = $this->earlyArrivalsQuery($filters)->get();
        $filename = "arrivees-avant-{$filters['beforeTime']}-{$filters['startDate']->format('Ymd')}-{$filters['endDate']->format('Ymd')}";
        $exportData = [
            "records" => $records,
            "periodLabel" => $filters["periodLabel"],
            "beforeTime" => $filters["beforeTime"],
            "department" => $filters["departmentId"] ? Department::find($filters["departmentId"])?->name : null,
            "employee" => $filters["employeeId"] ? Employee::find($filters["employeeId"])?->fullName() : null,
        ];

        if ($format === "pdf") {
            return Pdf::loadView("exports.early-arrivals", $exportData)
                ->setPaper("a4", "landscape")
                ->download($filename . ".pdf");
        }

        $filePath = $this->createXlsx($records);

        return response()->download($filePath, $filename . ".xlsx", [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        ])->deleteFileAfterSend(true);
    }

    private function createXlsx($records): string
    {
        $path = tempnam(sys_get_temp_dir(), "pointage-xlsx-");
        if ($path === false) {
            throw new \RuntimeException("Impossible de créer le fichier Excel temporaire.");
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new \RuntimeException("Impossible de préparer le fichier Excel.");
        }

        $headers = ["Employé", "Matricule", "Service", "Date", "Heure d'arrivée", "Site"];
        $xmlEscape = static fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, "UTF-8");
        $rows = [$headers];
        foreach ($records as $record) {
            $employee = $record->employee;
            $rows[] = [
                $employee?->fullName() ?? "Employé supprimé",
                $employee?->matricule ?? "",
                $employee?->department?->name ?? "",
                $record->recorded_at->format("Y-m-d"),
                $record->recorded_at->format("H:i"),
                $record->site?->name ?? "",
            ];
        }

        $sheetRows = "";
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $sheetRows .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $cell = chr(65 + $columnIndex) . $excelRow;
                $sheetRows .= '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">' . $xmlEscape($value) . '</t></is></c>';
            }
            $sheetRows .= '</row>';
        }

        $lastRow = count($rows);
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/><sheetData>' . $sheetRows . '</sheetData>'
            . '<autoFilter ref="A1:F' . $lastRow . '"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';

        $zip->addFromString("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString("_rels/.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString("xl/workbook.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Arrivées" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString("xl/_rels/workbook.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');
        $zip->addFromString("xl/worksheets/sheet1.xml", $sheetXml);
        $zip->close();

        return $path;
    }

    private function reportFilters(Request $request): array
    {
        $filters = $request->validate([
            "period" => ["sometimes", "in:weekly,monthly,quarterly,custom"],
            "start_date" => ["required_if:period,custom", "nullable", "date"],
            "end_date" => ["required_if:period,custom", "nullable", "date", "after_or_equal:start_date"],
            "before_time" => ["sometimes", "nullable", "date_format:H:i"],
            "department_id" => ["sometimes", "nullable", "integer", "exists:departments,id"],
            "employee_id" => ["sometimes", "nullable", "integer", "exists:employees,id"],
        ], [
            "period.in" => "Veuillez choisir une période valide.",
            "start_date.required_if" => "Veuillez choisir une date de début.",
            "start_date.date" => "La date de début n’est pas valide.",
            "end_date.required_if" => "Veuillez choisir une date de fin.",
            "end_date.date" => "La date de fin n’est pas valide.",
            "end_date.after_or_equal" => "La date de fin ne peut pas être antérieure à la date de début.",
            "before_time.date_format" => "Veuillez choisir une heure valide.",
            "department_id.exists" => "Le service sélectionné n’existe pas.",
            "employee_id.exists" => "L’employé sélectionné n’existe pas.",
        ]);

        $period = $filters["period"] ?? "weekly";
        $beforeTime = $filters["before_time"] ?? "09:00";
        if ($period === "custom") {
            $startDate = Carbon::parse($filters["start_date"])->startOfDay();
            $endDate = Carbon::parse($filters["end_date"])->endOfDay();
        } else {
            $now = now();
            $startDate = match ($period) {
                "monthly" => $now->copy()->startOfMonth()->startOfDay(),
                "quarterly" => $now->copy()->startOfQuarter()->startOfDay(),
                default => $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
            };
            $endDate = now()->endOfDay();
        }

        $periodLabel = match ($period) {
            "monthly" => "Ce mois-ci",
            "quarterly" => "Ce trimestre",
            "custom" => $startDate->format("d/m/Y") . " au " . $endDate->format("d/m/Y"),
            default => "Cette semaine",
        };

        return [
            "period" => $period,
            "periodLabel" => $periodLabel,
            "beforeTime" => $beforeTime,
            "startDate" => $startDate,
            "endDate" => $endDate,
            "departmentId" => $filters["department_id"] ?? null,
            "employeeId" => $filters["employee_id"] ?? null,
        ];
    }

    private function earlyArrivalsQuery(array $filters): Builder
    {
        return AttendanceRecord::with(["employee.department", "site"])
            ->where("status", "accepted")
            ->where("type", "arrival")
            ->whereBetween("recorded_at", [$filters["startDate"], $filters["endDate"]])
            ->whereTime("recorded_at", "<", $filters["beforeTime"])
            ->when($filters["departmentId"], fn (Builder $query, $departmentId) =>
                $query->whereHas("employee", fn (Builder $employees) => $employees->where("department_id", $departmentId))
            )
            ->when($filters["employeeId"], fn (Builder $query, $employeeId) => $query->where("employee_id", $employeeId))
            ->orderByDesc("recorded_at");
    }
}
