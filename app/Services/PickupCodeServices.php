<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PickupCodeServices {

    public function __invoke() {
        
        $users = User::all();
        foreach ($users as $user) {
            $user->pickup_code = Str::upper(Str::random(6));
            $user->save();
        }

        Log::info('Pickup codes updated on ' . now()->toDateTimeString());
        
    }
}