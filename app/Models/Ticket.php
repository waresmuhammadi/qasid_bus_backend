<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

   protected $fillable = [
    'trip_id',
    'seat_numbers',
      'seat_number',
    'bus_type',
    'name',
    'last_name',
    // ✅ new field
     'email',  
    'phone',
    'payment_method', // ✅ new field
      'status',
       'departure_date',
];
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }


    public function bus()
{
    return $this->belongsTo(Bus::class);
}

public function driver()
{
    return $this->belongsTo(Driver::class);
}



 protected $casts = [
    'seat_numbers' => 'array',
];



}
