<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'from',
        'to',
        'departure_time',
        'departure_date',
        'departure_terminal',
        'arrival_terminal',
        'prices' ,
        'bus_type',
         'all_days',
         'actual_departure_date',
         'chalan_number'
    ];
    
    protected $casts = [
    'bus_type' => 'array',  // ✅ ensures PHP array <-> JSON storage
      'prices' => 'array',
      'ratings' => 'array',
     


];
    // ✅ Add this relationship
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }



    // Add this method to get seat capacity based on bus type
// In your Trip model (App\Models\Trip.php)
public function getSeatCapacity()
{
    $capacity = 0;
    if (is_array($this->bus_type)) {
        foreach ($this->bus_type as $type) {
            if ($type === 'VIP') {
                $capacity += 36; // VIP bus has 36 seats
            } elseif ($type === '580') {
                $capacity += 51; // 580 bus has 51 seats
            }
        }
    }
    return $capacity;
}

// Add this method to get available seats
public function getAvailableSeats()
{
    $bookedSeats = $this->tickets()->pluck('seat_number')->toArray();
    $capacity = $this->getSeatCapacity();
    
    $availableSeats = [];
    for ($i = 1; $i <= $capacity; $i++) {
        if (!in_array($i, $bookedSeats)) {
            $availableSeats[] = $i;
        }
    }
    
    return $availableSeats;
}


public function ratings()
{
    return $this->hasMany(Rating::class);
}

public function averageRating()
{
    return $this->ratings()->avg('rate');
}

public function ratingsCount()
{
    return $this->ratings()->count();
}

    
}
