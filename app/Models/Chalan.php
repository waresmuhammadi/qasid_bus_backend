<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chalan extends Model
{
    use HasFactory;

    protected $fillable = [
        'chalan_number',
        'ticket_ids',
    ];

    protected $casts = [
        'ticket_ids' => 'array',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'chalan_id');
    }
}
