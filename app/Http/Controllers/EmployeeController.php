<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePasskeyActivationCode;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            "q" => ["nullable", "string", "max:100"],
            "department_id" => ["nullable", "integer", "exists:departments,id"],
        ]);
        $query = Employee::with(["department", "site", "user.passkeys"]);

        if ($search = $filters["q"] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where("first_name", "like", "%{$search}%")
                    ->orWhere("last_name", "like", "%{$search}%")
                    ->orWhere("matricule", "like", "%{$search}%");
            });
        }

        if ($departmentId = $filters["department_id"] ?? null) {
            $query->where("department_id", $departmentId);
        }

        $employees = $query->orderBy("last_name")->paginate(20)->withQueryString();

        $departments = Department::orderBy("name")->get();
        $sites = Site::orderBy("name")->get();

        return view("employees.index", compact("employees", "departments", "sites"));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "matricule" => ["required", "string", "max:50", "unique:employees,matricule"],
            "first_name" => ["required", "string", "max:255"],
            "last_name" => ["required", "string", "max:255"],
            "phone" => ["nullable", "string", "max:50"],
            "department_id" => ["nullable", "exists:departments,id"],
            "site_id" => ["nullable", "exists:sites,id"],
            "email" => ["required", "email", "unique:users,email"],
            "role" => ["required", Rule::in($request->user()->isSuperAdmin()
                ? ["employe", "responsable", "rh_admin", "super_admin"]
                : ["employe"])],
            "status" => ["required", "in:active,inactive"],
        ]);

        DB::transaction(function () use ($data) {
            $tempPassword = Str::password(10);

            $user = User::create([
                "name" => trim($data["first_name"] . " " . $data["last_name"]),
                "email" => $data["email"],
                "password" => Hash::make($tempPassword),
                "role" => $data["role"],
            ]);

            Employee::create([
                "user_id" => $user->id,
                "matricule" => $data["matricule"],
                "first_name" => $data["first_name"],
                "last_name" => $data["last_name"],
                "phone" => $data["phone"] ?? null,
                "department_id" => $data["department_id"] ?? null,
                "site_id" => $data["site_id"] ?? null,
                "status" => $data["status"],
            ]);

            // NOTE : en production, envoyer $tempPassword par email/SMS plutôt
            // que de le laisser en clair. Ici on l affiche une seule fois via flash.
            session()->flash("temp_password", $tempPassword);
            session()->flash("temp_password_email", $data["email"]);
        });

        return back()->with("status", "Employé créé avec succès.");
    }

    public function update(Request $request, Employee $employee)
    {
        abort_if($employee->user && ! $request->user()->isSuperAdmin() && $employee->user->role !== "employe", 403);

        $data = $request->validate([
            "matricule" => ["required", "string", "max:50", "unique:employees,matricule," . $employee->id],
            "first_name" => ["required", "string", "max:255"],
            "last_name" => ["required", "string", "max:255"],
            "phone" => ["nullable", "string", "max:50"],
            "department_id" => ["nullable", "exists:departments,id"],
            "site_id" => ["nullable", "exists:sites,id"],
            "status" => ["required", "in:active,inactive"],
            "role" => ["required", Rule::in($request->user()->isSuperAdmin()
                ? ["employe", "responsable", "rh_admin", "super_admin"]
                : ["employe"])],
        ]);

        if ($employee->user?->isSuperAdmin() && ($data["role"] !== "super_admin" || $data["status"] !== "active")) {
            $activeSuperAdmins = User::where("role", "super_admin")
                ->whereHas("employee", fn ($query) => $query->where("status", "active"))
                ->count();
            abort_if($activeSuperAdmins <= 1, 403);
        }

        $employee->update($data);

        if ($employee->user) {
            $employee->user->update([
                "name" => trim($data["first_name"] . " " . $data["last_name"]),
                "role" => $data["role"],
            ]);
        }

        return back()->with("status", "Employé mis à jour.");
    }

    public function generatePasskeyActivationCode(Request $request, Employee $employee)
    {
        abort_unless($employee->user?->role === 'employe', 422, 'La passkey ne concerne que les comptes employé.');

        $code = Str::upper(Str::random(16));

        DB::transaction(function () use ($request, $employee, $code) {
            $user = User::whereKey($employee->user_id)->lockForUpdate()->firstOrFail();
            $passkeyCount = $user->passkeys()->count();
            if ($passkeyCount > 0) {
                $user->passkeys()->delete();
                AuditLog::log('passkey_revoquee_admin', "nombre={$passkeyCount}; réinitialisation depuis la fiche employé", $employee->id);
            }

            EmployeePasskeyActivationCode::where('employee_id', $employee->id)
                ->whereNull('used_at')->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            $activationCode = EmployeePasskeyActivationCode::create([
                'employee_id' => $employee->id,
                'code_hash' => Hash::make($code),
                'generated_by' => $request->user()->id,
                'expires_at' => now()->addHours(24),
            ]);

            AuditLog::log('passkey_code_activation_genere', "code_id={$activationCode->id}; expire_at={$activationCode->expires_at->toIso8601String()}", $employee->id);
        });

        session()->flash('passkey_activation_code', $code);
        session()->flash('passkey_activation_employee', $employee->fullName());

        return back()->with('status', 'Code d’activation généré pour 24 heures. La passkey précédente a été révoquée.');
    }

    public function setDeviceFallback(Request $request, Employee $employee)
    {
        abort_unless($employee->user?->role === 'employe', 422, 'Le secours par appareil concerne uniquement les employés.');
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);

        if ((bool) $employee->device_fallback_enabled !== $enabled) {
            $employee->update(['device_fallback_enabled' => $enabled]);
            AuditLog::log('passkey_secours_appareil_modifie', $enabled ? 'Secours par appareil activé' : 'Secours par appareil désactivé', $employee->id);
        }

        return back()->with('status', $enabled ? 'Secours par appareil activé.' : 'Pointage par passkey obligatoire.');
    }

    public function destroy(Request $request, Employee $employee)
    {
        abort_if($employee->user_id === $request->user()->id, 403);
        abort_if($employee->user && ! $request->user()->isSuperAdmin() && $employee->user->role !== "employe", 403);
        if ($employee->user?->isSuperAdmin()) {
            $activeSuperAdmins = User::where("role", "super_admin")
                ->whereHas("employee", fn ($query) => $query->where("status", "active"))
                ->count();
            abort_if($activeSuperAdmins <= 1, 403);
        }

        $userId = $employee->user_id;
        $employee->delete();

        if ($userId) {
            User::where("id", $userId)->delete();
        }

        return back()->with("status", "Employé supprimé.");
    }
}
