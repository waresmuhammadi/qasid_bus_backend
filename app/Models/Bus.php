<?php

// app/Models/Bus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Laravel\Sanctum\HasApiTokens;

class Bus extends Model

{

     use HasApiTokens, HasFactory;
    protected $fillable = [
        'bus_no',
        'number_plate',
        'license_number',
        'type',
        'model',
        'company_id',
    ];
}
