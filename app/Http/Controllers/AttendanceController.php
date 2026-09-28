<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\GeoService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Page affichée après le scan du QR Code permanent.
     */
    public function scan(Request $request)
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return view('attendance.scan', [
                'error' => "Aucun profil employé n'est associé à ce compte. Contactez un administrateur.",
                'employee' => null,
                'site' => null,
                'nextAction' => null,
                'lastRecord' => null,
            ]);
        }

        if ($employee->status !== 'active') {
            return view('attendance.scan', [
                'error' => 'Votre compte employé est désactivé.',
                'employee' => $employee,
                'site' => null,
                'nextAction' => null,
                'lastRecord' => null,
            ]);
        }

        $site = $employee->site;
        $lastRecordToday = $employee->lastRecordToday();
        $nextAction = $lastRecordToday && $lastRecordToday->type === 'arrival' ? 'departure' : 'arrival';

        return view('attendance.scan', [
            'error' => $site ? null : "Aucun site n'est assigné à votre profil. Contactez un administrateur.",
            'employee' => $employee,
            'site' => $site,
            'nextAction' => $nextAction,
            'lastRecord' => $lastRecordToday,
        ]);
    }

    /**
     * Point d'entrée AJAX appelé par la page de pointage.
     */
    public function check(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ]);

        $employee = $request->user()->employee;

        if (! $employee || $employee->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'Profil employé introuvable ou désactivé.',
            ], 403);
        }

        $site = $employee->site;

        if (! $site) {
            return response()->json([
                'ok' => false,
                'message' => "Aucun site n'est assigné à votre profil.",
            ], 422);
        }

        return DB::transaction(function () use ($data, $employee, $site) {
            // Relit le dernier pointage du jour à l'intérieur de la transaction
            // pour limiter les risques de double clic / double soumission.
            $lastToday = AttendanceRecord::where('employee_id', $employee->id)
                ->where('status', 'accepted')
                ->whereDate('recorded_at', now()->toDateString())
                ->lockForUpdate()
                ->latest('recorded_at')
                ->first();

            $type = ($lastToday && $lastToday->type === 'arrival') ? 'departure' : 'arrival';

            $distance = GeoService::distanceMeters(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) $site->latitude,
                (float) $site->longitude
            );

            $withinZone = $distance <= (int) $site->radius_m;

            if (! $withinZone) {
                AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'site_id' => $site->id,
                    'type' => $type,
                    'recorded_at' => now(),
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'distance_meters' => $distance,
                    'device_id' => $data['device_id'] ?? null,
                    'status' => 'rejected',
                    'rejection_reason' => 'hors_zone',
                ]);

                AuditLog::log('pointage_refuse_hors_zone', "distance={$distance}m", $employee->id);

                return response()->json([
                    'ok' => false,
                    'message' => "Vous devez être sur le site de l'entreprise pour pointer.",
                ], 422);
            }

            if ($type === 'departure' && ! $lastToday) {
                AuditLog::log('pointage_refuse_sequence', 'sortie_sans_arrivee', $employee->id);

                return response()->json([
                    'ok' => false,
                    'message' => "Aucune arrivée enregistrée aujourd'hui.",
                ], 422);
            }

            $record = AttendanceRecord::create([
                'employee_id' => $employee->id,
                'site_id' => $site->id,
                'type' => $type,
                'recorded_at' => now(),
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'distance_meters' => $distance,
                'device_id' => $data['device_id'] ?? null,
                'status' => 'accepted',
            ]);

            AuditLog::log('pointage_accepte_' . $type, "distance={$distance}m", $employee->id);

            return response()->json([
                'ok' => true,
                'type' => $type,
                'label' => $type === 'arrival' ? 'arrivée' : 'sortie',
                'time' => $record->recorded_at->format('H:i'),
                'message' => 'Présence enregistrée à ' . $record->recorded_at->format('H:i') . '.',
            ]);
        });
    }

    /**
     * Historique personnel de l'employé connecté.
     */
    public function history(Request $request)
    {
        $employee = $request->user()->employee;
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:arrival,departure'],
        ]);

        $records = $employee ? $this->filteredHistory($employee, $filters) : collect();

        return view('attendance.history', compact('records', 'employee'));
    }

    /** Historique consultable par l'administration pour un employé donné. */
    public function employeeHistory(Request $request, Employee $employee)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:arrival,departure'],
        ]);
        $records = $this->filteredHistory($employee, $filters);

        return view('attendance.history', compact('records', 'employee'));
    }

    private function filteredHistory(Employee $employee, array $filters)
    {
        return $employee->attendanceRecords()
            ->where('status', 'accepted')
            ->with('site')
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('recorded_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('recorded_at', '<=', $date))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->latest('recorded_at')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * Page admin : QR Code permanent à imprimer et afficher à l'entrée.
     */
    public function qrcode()
    {
        $scanUrl = route('attendance.scan');
        $qrCode = new QrCode(data: $scanUrl, size: 260, margin: 2);
        $qrImage = (new SvgWriter())->write($qrCode)->getDataUri();

        return view('attendance.qrcode', compact('scanUrl', 'qrImage'));
    }
}
