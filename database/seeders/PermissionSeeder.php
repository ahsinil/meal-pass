<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ALL PERMISSION
        $allPermissions = [
            // Dashboard
            'view_active_session_stats',
            'view_all_stats',

            // Data Karyawan
            'view_employees',
            'create_employees',
            'update_employees',
            'delete_employees',
            'import_employees',
            'export_employees',

            // Data Petugas
            'view_staffs',
            'create_staffs',
            'update_staffs',
            'delete_staffs',
            'export_staffs',

            // Data Makanan
            'view_meals',
            'create_meals',
            'update_meals',
            'delete_meals',
            'export_meals',

            // Data Pengambilan
            'view_pickups',
            'create_pickups',
            'update_pickups',
            'delete_pickups',
            'export_pickups',

            // Scan
            'access_scan',
            'access_scan_history',

            // Pass
            'access_pass',
            'access_pass_history',

            // Setting Profil
            'view_profile_settings',
            'update_profile',
            'update_password',
            'reset_password',
            'delete_account',

            // Setting Pengguna

            // Setting Aplikasi
        ];
        foreach ($allPermissions as $permission) {
            Permission::create([
                'name' => $permission
            ]);
        }
        // SUPER ADMIN
        $roleSuper = Role::create([
            'name' => 'superadmin',
        ])->givePermissionTo($allPermissions);
        // CREATE DEFAULT USER
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'phone' => '0812341234',
            'email' => 'ganti@email.local',
            'password' => Hash::make('12345678'),
            'is_admin' => 1,
            'is_active' => 1
        ]);
        $superAdmin->assignRole($roleSuper);

        // ADMIN PERMISSION
        $adminPermissions = [
            // Dashboard
            'view_active_session_stats',
            'view_all_stats',

            // Data Karyawan
            'view_employees',
            'create_employees',
            'update_employees',
            'delete_employees',
            // 'import_employees',
            'export_employees',

            // Data Petugas
            'view_staffs',
            'create_staffs',
            'update_staffs',
            // 'delete_staffs',
            'export_staffs',

            // Data Makanan
            'view_meals',
            'create_meals',
            'update_meals',
            'delete_meals',
            'export_meals',

            // Data Pengambilan
            'view_pickups',
            'create_pickups',
            'update_pickups',
            'delete_pickups',
            'export_pickups',

            // // Scan
            // 'access_scan',
            // 'access_scan_history',

            // // Pass
            // 'access_pass',
            // 'access_pass_history',

            // Setting Profil
            'view_profile_settings',
            'update_profile',
            'update_password',
            'reset_password',
            'delete_account',

            // Setting Pengguna

            // Setting Aplikasi
        ];
        $roleAdmin = Role::create([
            'name' => 'admin',
        ])->givePermissionTo($adminPermissions);

        $admin = User::create([
            'name' => 'Admin',
            'phone' => '08123456789',
            'email' => 'admin@email.local',
            'password' => Hash::make('12345678'),
            'is_admin' => 1,
            'is_active' => 1
        ]);
        $admin->assignRole($roleAdmin);

        // STAFF
        $staffPermissions = [
            // Scan
            'access_scan',
            'access_scan_history',
            'view_profile_settings',
            'update_profile',
            'update_password',
            'reset_password',
        ];
        Role::create([
            'name' => 'staff',
        ])->givePermissionTo($staffPermissions);

        // EMPLOYEE
        $employeePermissions = [
            // Pass
            'access_pass',
            'access_pass_history',
            'view_profile_settings',
            'update_profile',
            'update_password',
            'reset_password',
        ];
        Role::create([
            'name' => 'employee',
        ])->givePermissionTo($employeePermissions);
    }
}
