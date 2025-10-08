<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealWindow extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'location',
        'is_active',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(MealSession::class, 'meal_window_id');
    }
}
