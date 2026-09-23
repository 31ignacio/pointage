<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PointageDemoSeeder extends Seeder
{
    /**
     * Crée un compte super admin et un site de démonstration.
     * Adapter les coordonnées GPS au vrai site de l'entreprise avant la mise en production.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]
        );

        $department = Department::firstOrCreate(['name' => 'Direction']);

        $site = Site::firstOrCreate(
            ['name' => 'Siège'],
            [
                'address' => 'À compléter',
                'latitude' => 6.3703,
                'longitude' => 2.3912,
                'radius_m' => 100,
            ]
        );

        Employee::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'matricule' => 'ADM-0001',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'department_id' => $department->id,
                'site_id' => $site->id,
                'status' => 'active',
            ]
        );
    }
}
