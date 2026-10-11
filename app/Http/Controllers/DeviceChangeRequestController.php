<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceChangeRequest;
use App\Models\DeviceIdOwner;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class DeviceChangeRequestController extends Controller
{
    public function index()
    {
        $requests = DeviceChangeRequest::with('employee.user')
            ->where('status', 'pending')->latest()->paginate(30);

        return view('devices.requests', compact('requests'));
    }

    public function approve(Request $request, DeviceChangeRequest $deviceChangeRequest)
    {
        $owner = DeviceIdOwner::where('device_id', $deviceChangeRequest->new_device_id)->first();
        if ($owner && $owner->employee_id !== $deviceChangeRequest->employee_id) {
            return back()->with('error', 'Cet appareil a déjà été autorisé pour un autre utilisateur. La demande n’a pas été validée.');
        }

        try {
            DB::transaction(function () use ($request, $deviceChangeRequest) {
            $change = DeviceChangeRequest::whereKey($deviceChangeRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($change->status !== 'pending', 409, 'Cette demande a déjà été traitée.');

            $employee = Employee::with('user')->findOrFail($change->employee_id);
            abort_if($employee->user?->role !== 'employe', 422, 'Le compte n’est pas un employé soumis à cette règle.');

            $device = Device::where('employee_id', $employee->id)->lockForUpdate()->first();
            if ($change->old_device_id !== null && $device?->device_id !== $change->old_device_id) {
                abort(409, 'Cette demande ne correspond plus à l’appareil actuellement autorisé.');
            }
            abort_if(Device::where('device_id', $change->new_device_id)
                ->where('employee_id', '!=', $employee->id)->exists(), 422,
                'Cet appareil est déjà autorisé pour un autre utilisateur.');
            $owner = DeviceIdOwner::where('device_id', $change->new_device_id)->first();
            abort_if($owner && $owner->employee_id !== $employee->id, 422,
                'Cet appareil a déjà été autorisé pour un autre utilisateur.');
            if (! $owner) {
                DeviceIdOwner::create(['device_id' => $change->new_device_id, 'employee_id' => $employee->id]);
            }
            if ($device) {
                $device->update([
                    'device_id' => $change->new_device_id,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
            } else {
                Device::create([
                    'employee_id' => $employee->id,
                    'device_id' => $change->new_device_id,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);
            }

            $change->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            AuditLog::log('appareil_changement_valide', "demande={$change->id}; nouvel_appareil={$change->new_device_id}", $employee->id);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return back()->with('error', 'Cet appareil a été associé à un autre utilisateur entre-temps. Aucun changement n’a été effectué.');
            }
            throw $exception;
        }

        return back()->with('status', 'Changement d’appareil validé.');
    }

    public function reject(Request $request, DeviceChangeRequest $deviceChangeRequest)
    {
        DB::transaction(function () use ($request, $deviceChangeRequest) {
            $change = DeviceChangeRequest::whereKey($deviceChangeRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($change->status !== 'pending', 409, 'Cette demande a déjà été traitée.');
            $change->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            AuditLog::log('appareil_changement_rejete', "demande={$change->id}", $change->employee_id);
        });

        return back()->with('error', 'Demande de changement d’appareil rejetée. L’appareil actuellement autorisé reste inchangé.');
    }

    public function reset(Request $request, Employee $employee)
    {
        abort_unless($employee->user?->role === 'employe', 422, 'Seuls les comptes employé sont concernés.');

        DB::transaction(function () use ($request, $employee) {
            Device::where('employee_id', $employee->id)->delete();
            DeviceChangeRequest::where('employee_id', $employee->id)->where('status', 'pending')
                ->update(['status' => 'cancelled', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            AuditLog::log('appareil_reinitialise', 'Réinitialisation urgente par un administrateur', $employee->id);
        });

        return back()->with('status', 'Appareil réinitialisé. Le prochain pointage de l’employé enregistrera son nouvel appareil.');
    }
}
