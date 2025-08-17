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
     Route::get('/trips', [TripController::class, 'index']);


});

Route::get('/buses', [BusController::class, 'index']);
Route::get('/buses/{id}', [BusController::class, 'show']);



    Route::get('/trips/{id}', [TripController::class, 'show']);

    // routes/api.php
Route::get('companies/{id}/trips', [TripController::class, 'publicIndex']);



