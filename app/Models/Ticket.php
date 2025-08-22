<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

   protected $fillable = [
    'trip_id',
    'seat_number',
    'name',
    'last_name',   // ✅ new field
    'phone',
    'payment_method', // ✅ new field
      'status',
];
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
