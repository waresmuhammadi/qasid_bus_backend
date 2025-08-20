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
    ];

    // ✅ Add this relationship
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    
}
