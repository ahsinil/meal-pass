<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealWindow extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'location',
        'is_active',
    ];
}
