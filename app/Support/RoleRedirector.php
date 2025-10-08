<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class RoleRedirector
{
    public static function urlFor(Authenticatable $user): string
    {
        if ($user->hasRole('employee')) {
            return route('pass');
        }

        if ($user->hasRole('staff')) {
            return route('pickup.scanner');
        }

        if ($user->hasRole('admin')) {
            return route('dashboard');
        }

        if ($user->hasRole('superadmin')) {
            return route('dashboard');
        }

        // fallback
        return abort(403);
    }
}
