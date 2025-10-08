<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Route::get('/', function () {
//     return view('welcome');
// })->name('home');

// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Volt::route('/', 'dashboard')->name('home')->middleware('role:superadmin|admin');
    Route::get('/', function () {
        $user = auth()->user();
        abort_unless($user, 403);
        return redirect(\App\Support\RoleRedirector::urlFor($user));
    })->name('home');
    Volt::route('dashboard', 'dashboard')->name('dashboard')->middleware('role:superadmin|admin');
    Volt::route('employees', 'employees.list')->name('employees')->middleware('can:view_employees');
    Volt::route('officers', 'officer.list')->name('officers')->middleware('can:view_staffs');
    Volt::route('meals', 'meal.list')->name('meals')->middleware('can:view_meals');
    Volt::route('pickups', 'pickup.list')->name('pickups')->middleware('can:view_pickups');
    
    Volt::route('pickup/scanner', 'officer.scanner')->name('pickup.scanner')->middleware('can:access_scan');
    Volt::route('pickup/history', 'officer.history')->name('pickup.history')->middleware('can:access_scan_history');
    Volt::route('officer/settings', 'officer.settings')->name('officer.settings')->middleware('can:view_profile_settings');
    
    Volt::route('pass', 'employees.pass')->name('pass')->middleware('can:access_pass');
    Volt::route('pass/history', 'employees.history')->name('pass.history')->middleware('can:access_pass_history');
    Volt::route('employee/settings', 'employees.settings')->name('employee.settings')->middleware('can:view_profile_settings');
    
    Route::redirect('settings', 'settings/appearance');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile')->middleware('can:view_profile_settings');
    Volt::route('settings/password', 'settings.password')->name('settings.password')->middleware('can:update_password');
    // Volt::route('user/settings', 'simple-settings')->name('simple.settings');
});

require __DIR__.'/auth.php';
