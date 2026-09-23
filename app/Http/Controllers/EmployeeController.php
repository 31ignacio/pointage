<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(["department", "site", "user"]);

        if ($search = $request->get("q")) {
            $query->where(function ($q) use ($search) {
                $q->where("first_name", "like", "%{$search}%")
                    ->orWhere("last_name", "like", "%{$search}%")
                    ->orWhere("matricule", "like", "%{$search}%");
            });
        }

        if ($departmentId = $request->get("department_id")) {
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
            "role" => ["required", "in:employe,responsable,rh_admin,super_admin"],
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
        $data = $request->validate([
            "matricule" => ["required", "string", "max:50", "unique:employees,matricule," . $employee->id],
            "first_name" => ["required", "string", "max:255"],
            "last_name" => ["required", "string", "max:255"],
            "phone" => ["nullable", "string", "max:50"],
            "department_id" => ["nullable", "exists:departments,id"],
            "site_id" => ["nullable", "exists:sites,id"],
            "status" => ["required", "in:active,inactive"],
            "role" => ["required", "in:employe,responsable,rh_admin,super_admin"],
        ]);

        $employee->update($data);

        if ($employee->user) {
            $employee->user->update([
                "name" => trim($data["first_name"] . " " . $data["last_name"]),
                "role" => $data["role"],
            ]);
        }

        return back()->with("status", "Employé mis à jour.");
    }

    public function destroy(Employee $employee)
    {
        $userId = $employee->user_id;
        $employee->delete();

        if ($userId) {
            User::where("id", $userId)->delete();
        }

        return back()->with("status", "Employé supprimé.");
    }
}
