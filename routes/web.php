<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


use App\Http\Controllers\TicketController;

Route::get('/payment/failure/{ticketId}', [TicketController::class, 'paymentFailure']);
use Illuminate\Support\Facades\Mail;

Route::get('/test-mail', function () {
    Mail::raw('Hello from Wardak Baba Travels 🚍', function ($message) {
        $message->to('muhammadhares11@gmail.com')
                ->subject('SMTP Test Email');
    });

    return 'Email sent!';
});
