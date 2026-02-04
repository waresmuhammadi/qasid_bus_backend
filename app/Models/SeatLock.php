<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'bus_type', 
        'departure_date',
        'seat_number',
        'locked_for'
    ];

    protected $casts = [
        'departure_date' => 'date'
    ];
}