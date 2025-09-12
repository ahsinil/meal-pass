<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, CanResetPassword, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'department',
        'employee_code',
        'pickup_code',
        'is_active',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Kirim tautan reset password via WhatsApp (Fonnte).
     */
    public function sendPasswordResetNotification($token)
    {
        // tautan reset
        $resetUrl = url(
            route('password.reset', [
                'token' => $token,
                'phone' => $this->phone,
            ], false)
        );

        // pesan WA
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $message = "Hallo {$this->name},\n\nBerikut tautan untuk reset password akun Anda:\n{$resetUrl}\n\nTautan berlaku {$minutes} menit. Abaikan pesan ini jika Anda tidak meminta reset.";

        // kirim WA via Fonnte
        $fonnte = new \App\Fonnte();
        $fonnte->send($this->phone, $message);
    }

    public function scopeKaryawan($query)
    {
        return $query->where('is_admin', 0);
    }
    
    public function scopePetugas($query)
    {
        return $query->where('is_admin', 1);
    }
}
