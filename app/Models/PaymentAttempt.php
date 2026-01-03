<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'trip_id',
        'seat_numbers',
        'name',
        'phone',
        'email',
        'bus_type',
        'departure_date',
        'coupon_code',
        'discount_amount',
        'final_price',
        'status',
        'from_website',
    ];

    protected $casts = [
        'seat_numbers' => 'array',
        'departure_date' => 'date',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
