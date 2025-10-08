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
    Volt::route('/', 'dashboard')->name('home');
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Volt::route('employees', 'employees.list')->name('employees')->middleware('can:view_employees');
    Volt::route('officers', 'officer.list')->name('officers')->middleware('can:view_staffs');
    Volt::route('meals', 'meal.list')->name('meals')->middleware('can:view_meals');
    Volt::route('pickups', 'pickup.list')->name('pickups')->middleware('can:view_pickups');
    
    Volt::route('pickup/scanner', 'officer.scanner')->name('pickup.scanner');
    Volt::route('pickup/history', 'officer.history')->name('pickup.history');

    Volt::route('pass', 'employees.pass')->name('pass');
    Volt::route('pass/history', 'employees.history')->name('pass.history');
    
    Route::redirect('settings', 'settings/appearance');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
