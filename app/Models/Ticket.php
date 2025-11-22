<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

   protected $fillable = [
    'trip_id',
    'ticket_number',
    'seat_numbers',
      'seat_number',
    'bus_type',
         'cleaner_id',
    'name',
   'bus_number_plate', // Add this
    'father_name',
     'province',
    // ✅ new field
     'email',  
     'address',
    'phone',
    'payment_method', // ✅ new field
      'payment_status', // ✅ MAKE SURE THIS IS HERE
      'status',
        'from',
       'departure_date',
       'transaction_id',
       'coupon_code',
       'final_price'
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




public function chalan()
{
    return $this->belongsTo(Chalan::class, 'chalan_id');
}

public function cleaner()
    {
        return $this->belongsTo(Cleaner::class);
    }


}
