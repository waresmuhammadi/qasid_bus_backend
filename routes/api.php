<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripController;
use App\Http\Controllers\authController;
use App\Http\Controllers\BusController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');





Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);



// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/buses', [BusController::class, 'store']);
  
  
// });



// Route::get('/buses', [BusController::class, 'index']);
// Route::get('/buses/{id}', [BusController::class, 'show']);
// Route::post('/buses', [BusController::class, 'store']);
// Route::put('/buses/{id}', [BusController::class, 'update']);
// Route::delete('/buses/{id}', [BusController::class, 'destroy']);

Route::middleware('company.auth')->group(function () {
    Route::post('/buses', [BusController::class, 'store']);
    Route::put('/buses/{id}', [BusController::class, 'update']);
    Route::delete('/buses/{id}', [BusController::class, 'destroy']);


       // Trip routes
   
    Route::post('/trips', [TripController::class, 'store']);
    Route::put('/trips/{id}', [TripController::class, 'update']);
    Route::delete('/trips/{id}', [TripController::class, 'destroy']);

     Route::post('/trips/{tripId}/reserve', [TripController::class, 'reserve']);
Route::get('/trips/{tripId}/availability', [TripController::class, 'availability']);


});
     Route::get('/trips', [TripController::class, 'index']);
     Route::get('/public/trips', [TripController::class, 'publicIndex']);


Route::get('/buses', [BusController::class, 'index']);

Route::get('/buses/{id}', [BusController::class, 'show']);



    Route::get('/trips/{id}', [TripController::class, 'show']);
    // routes/api.php
Route::get('companies/{id}/trips', [TripController::class, 'publicIndex']);



use App\Http\Controllers\DriverController;

// Get all drivers
Route::get('/drivers', [DriverController::class, 'getAllDrivers']);

// Get single driver
Route::get('/drivers/{id}', [DriverController::class, 'getDriver']);

// Create a new driver
Route::post('/drivers', [DriverController::class, 'createDriver']);

// Update a driver
Route::put('/drivers/{id}', [DriverController::class, 'updateDriver']);

// Delete a driver
Route::delete('/drivers/{id}', [DriverController::class, 'deleteDriver']);

use App\Http\Controllers\TicketController;

Route::get('/trips/{tripId}/seats', [TicketController::class, 'availableSeats']);

Route::post('/trips/{tripId}/book', [TicketController::class, 'book']);

Route::get('/trips/{tripId}/tickets', [TicketController::class, 'tripTickets']);
Route::get('/trips-with-tickets', [TicketController::class, 'allTripsWithTickets']);


  Route::put('/tickets/{ticketId}/assign', [TicketController::class, 'assignBusAndDriver']);

    // ✅ Assign bus & driver to multiple tickets at once
    Route::put('assign-bulk', [TicketController::class, 'bulkAssignBusAndDriver']);