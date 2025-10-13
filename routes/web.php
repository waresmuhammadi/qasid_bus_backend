<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


use App\Http\Controllers\TicketController;

Route::get('/done/{ticketId}', [TicketController::class, 'paymentSuccess']);
Route::get('/payment/failure/{ticketId}', [TicketController::class, 'paymentFailure']);
