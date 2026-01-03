<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cleaner extends Model
{
    use HasFactory;

    protected $fillable = ['cleaner_name', 'cleaner_phone'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
