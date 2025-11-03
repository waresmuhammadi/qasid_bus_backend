<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chalan extends Model
{
    protected $fillable = [
        'chalan_number',
        'ticket_ids'
    ];

    protected $casts = [
        'ticket_ids' => 'array', // automatically casts JSON to array
    ];
}
