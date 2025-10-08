<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pickup extends Model
{
    use HasFactory;
    
    protected $table = 'pickups';

    protected $fillable = [
        'officer_id',
        'picked_by',
        'meal_session_id',
        'picked_at',
        'method',
        'overriden',
        'overriden_reason',
    ];

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_id', 'id');
    }

    public function picker()
    {
        return $this->belongsTo(User::class, 'picked_by', 'id');
    }

    public function session()
    {
        return $this->belongsTo(MealSession::class, 'meal_session_id', 'id');
    }

    public function getDateIndoAttribute()
    {
        return Carbon::parse($this->picked_at)->translatedFormat('d F Y');
    }

    public function getTimeIndoAttribute()
    {
        return Carbon::parse($this->picked_at)->translatedFormat('H:i');
    }
}
