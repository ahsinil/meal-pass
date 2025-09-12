<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MealSession extends Model
{
    protected $fillable = [
        'date',
        'meal_window_id',
        'qty',
        'notes',
        'is_active',
    ];

    public function mealTime()
    {
        return $this->belongsTo(MealWindow::class, 'meal_window_id');
    }

    public function getDateIndoAttribute()
    {
        return Carbon::parse($this->date)->translatedFormat('d F Y');
    }
}
