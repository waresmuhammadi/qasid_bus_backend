<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'from',
        'to',
        'departure_time',
        'departure_date',
        'departure_terminal',
        'arrival_terminal',
        'company_id', // link to company
        // 'bus_id' // optional if you want to link trip to a bus
    ];

    // Trip belongs to a company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Optional: trip belongs to a bus
    /*
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
    */
}
