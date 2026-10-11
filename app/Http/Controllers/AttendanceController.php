<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Device;
use App\Models\DeviceChangeRequest;
use App\Models\DeviceIdOwner;
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
                'passkeyEnabled' => false,
            ]);
        }

        if ($employee->status !== 'active') {
            return view('attendance.scan', [
                'error' => 'Votre compte employé est désactivé.',
                'employee' => $employee,
                'site' => null,
                'nextAction' => null,
                'lastRecord' => null,
                'passkeyEnabled' => false,
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
            'passkeyEnabled' => $request->user()->hasPasskeysEnabled(),
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

        $isEmployee = $request->user()->role === 'employe';
        $usesDeviceFallback = $isEmployee && $employee->device_fallback_enabled;

        if ($usesDeviceFallback && empty($data['device_id'])) {
            return response()->json(['ok' => false, 'message' => 'Identifiant de cet appareil manquant. Actualisez la page et réessayez.'], 422);
        }

        if ($isEmployee && ! $usesDeviceFallback) {
            if (! $request->user()->hasPasskeysEnabled()) {
                $request->session()->forget(['attendance.passkey_verified_at', 'attendance.passkey_verified_user_id']);
                AuditLog::log('pointage_refuse_passkey_absente', 'Aucune passkey enregistrée', $employee->id);

                return response()->json(['ok' => false, 'message' => 'Activez votre empreinte pour pointer. Contactez votre administrateur.'], 403);
            }

            $verifiedAt = $request->session()->pull('attendance.passkey_verified_at');
            $verifiedUserId = $request->session()->pull('attendance.passkey_verified_user_id');
            if (! is_numeric($verifiedAt) || now()->timestamp - (int) $verifiedAt > 120 || (string) $verifiedUserId !== (string) $request->user()->id) {
                AuditLog::log('pointage_echec_verification_biometrique', 'Pointage sans vérification récente et liée à cette session utilisateur', $employee->id);

                return response()->json(['ok' => false, 'message' => 'Vérifiez votre empreinte avant de pointer.'], 403);
            }
        }

        $site = $employee->site;

        if (! $site) {
            return response()->json([
                'ok' => false,
                'message' => "Aucun site n'est assigné à votre profil.",
            ], 422);
        }

        return DB::transaction(function () use ($data, $employee, $site, $request) {
            // Sérialise les pointages concurrents de cet employé pour éviter de
            // créer deux appareils de confiance au premier pointage.
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $device = null;
            if ($request->user()->role === 'employe' && $employee->device_fallback_enabled) {
                $device = Device::where('employee_id', $employee->id)->lockForUpdate()->first();
                if ($device && ($device->status !== 'active' || ! hash_equals($device->device_id, $data['device_id']))) {
                    $recentRequest = DeviceChangeRequest::where('employee_id', $employee->id)
                        ->where('new_device_id', $data['device_id'])->latest()->first();
                    AuditLog::log('pointage_refuse_appareil_inconnu', "appareil={$data['device_id']}", $employee->id);

                    return response()->json([
                        'ok' => false,
                        'message' => 'Vous essayez d’effectuer votre pointage depuis un appareil différent de votre appareil habituel.',
                        'device_change_required' => true,
                        'device_change_rejected' => $recentRequest?->status === 'rejected',
                    ], 409);
                }
            }
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

            $approvedChange = null;
            if ($request->user()->role === 'employe' && $employee->device_fallback_enabled && $device) {
                $approvedChange = DeviceChangeRequest::where('employee_id', $employee->id)
                    ->where('new_device_id', $device->device_id)
                    ->where('status', 'approved')->whereNull('employee_notified_at')
                    ->latest()->first();
            }

            if ($request->user()->role === 'employe' && $employee->device_fallback_enabled && ! $device) {
                $owner = DeviceIdOwner::where('device_id', $data['device_id'])->first();
                if (($owner && $owner->employee_id !== $employee->id) || Device::where('device_id', $data['device_id'])->exists()) {
                    AuditLog::log('pointage_refuse_appareil_deja_associe', "appareil={$data['device_id']}", $employee->id);

                    return response()->json([
                        'ok' => false,
                        'message' => 'Cet appareil est déjà autorisé pour un autre utilisateur. Contactez un administrateur.',
                    ], 409);
                }

                DeviceIdOwner::firstOrCreate(['device_id' => $data['device_id']], ['employee_id' => $employee->id]);

                Device::create([
                    'employee_id' => $employee->id,
                    'device_id' => $data['device_id'],
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
                AuditLog::log('appareil_fiable_enregistre', "appareil={$data['device_id']}", $employee->id);
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

            if ($approvedChange) {
                $approvedChange->update(['employee_notified_at' => now()]);
            }

            $message = 'Présence enregistrée à ' . $record->recorded_at->format('H:i') . '.';
            if ($approvedChange) {
                $message = 'Votre changement d’appareil a été accepté. ' . $message;
            }

            return response()->json([
                'ok' => true,
                'type' => $type,
                'label' => $type === 'arrival' ? 'arrivée' : 'sortie',
                'time' => $record->recorded_at->format('H:i'),
                'message' => $message,
            ]);
        });
    }

    /** Crée une demande uniquement après confirmation explicite dans le modal. */
    public function requestDeviceChange(Request $request)
    {
        $data = $request->validate(['device_id' => ['required', 'string', 'max:191']]);
        $user = $request->user();
        $employee = $user->employee;
        abort_unless($user->role === 'employe' && $employee && $employee->status === 'active' && $employee->device_fallback_enabled, 403);

        $change = DB::transaction(function () use ($request, $data, $employee) {
            $lockedEmployee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $device = Device::where('employee_id', $lockedEmployee->id)->lockForUpdate()->first();
            abort_unless($device && $device->status === 'active', 409, 'Aucun appareil de confiance actif n’est enregistré.');
            abort_if(hash_equals($device->device_id, $data['device_id']), 409, 'Cet appareil est déjà autorisé.');

            abort_if(Device::where('device_id', $data['device_id'])
                ->where('employee_id', '!=', $lockedEmployee->id)->exists(), 422,
                'Cet appareil est déjà associé à un autre utilisateur.');
            $owner = DeviceIdOwner::where('device_id', $data['device_id'])->first();
            abort_if($owner && $owner->employee_id !== $lockedEmployee->id, 422,
                'Cet appareil a déjà été autorisé pour un autre utilisateur.');

            $change = DeviceChangeRequest::firstOrCreate([
                'employee_id' => $lockedEmployee->id,
                'old_device_id' => $device->device_id,
                'new_device_id' => $data['device_id'],
                'status' => 'pending',
            ], [
                'new_device_user_agent' => substr((string) $request->userAgent(), 0, 500),
                'new_device_ip' => $request->ip(),
            ]);

            if ($change->wasRecentlyCreated) {
                AuditLog::log('demande_changement_appareil_creee', "demande={$change->id}; nouvel_appareil={$data['device_id']}", $lockedEmployee->id);
            }

            return $change;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Votre demande a été envoyée à l’administrateur. Elle est en attente de validation.',
            'status' => $change->status,
        ]);
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
