<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealSession extends Model
{
    use HasFactory;

    protected $table = 'meal_sessions';
    protected $fillable = [
        'date',
        'meal_window_id',
        'qty',
        'notes',
        'is_active',
    ];

    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }

    public function getTotalPickupAttribute()
    {
        return $this->pickups()->count();
    }

    public function mealTime()
    {
        return $this->belongsTo(MealWindow::class, 'meal_window_id');
    }

    public function getDateIndoAttribute()
    {
        return Carbon::parse($this->date)->translatedFormat('d F Y');
    }
}
