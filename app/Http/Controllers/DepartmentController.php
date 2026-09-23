<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount("employees")->orderBy("name")->paginate(20);

        return view("departments.index", compact("departments"));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "name" => ["required", "string", "max:255", "unique:departments,name"],
        ]);

        Department::create($data);

        return back()->with("status", "Service créé avec succès.");
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            "name" => ["required", "string", "max:255", "unique:departments,name," . $department->id],
        ]);

        $department->update($data);

        return back()->with("status", "Service mis à jour.");
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return back()->with("status", "Service supprimé.");
    }
}
