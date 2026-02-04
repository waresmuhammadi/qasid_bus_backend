<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripController;
use App\Http\Controllers\authController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\cleanerController;
use App\Http\Controllers\PaymentAttemptController;

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



Route::post('/cleaners', [CleanerController::class, 'createCleaner']);
Route::get('/cleaners/{id}', [CleanerController::class, 'getCleanerById']);
Route::put('/cleaners/{id}', [CleanerController::class, 'updateCleaner']);
Route::delete('/cleaners/{id}', [CleanerController::class, 'deleteCleaner']);






       // Trip routes
   

    Route::put('/trips/{id}', [TripController::class, 'update']);
    Route::delete('/trips/{id}', [TripController::class, 'destroy']);

     Route::post('/trips/{tripId}/reserve', [TripController::class, 'reserve']);
Route::get('/trips/{tripId}/availability', [TripController::class, 'availability']);

    
Route::prefix('coupons')->group(function () {
    Route::get('/', [CouponController::class, 'index']);          // List all
    Route::get('/{id}', [CouponController::class, 'show']);       // Single coupon
    Route::post('/', [CouponController::class, 'store']);         // Create
    Route::put('/{id}', [CouponController::class, 'update']);     // Update
    Route::delete('/{id}', [CouponController::class, 'destroy']); // Delete
});


});
     Route::get('/trips', [TripController::class, 'index']);
     Route::get('/public/trips', [TripController::class, 'publicIndex']);
     Route::get('companies/search-trips', [TripController::class, 'Mobiletrips']);

     Route::get('companies/dropdown-search', [TripController::class, 'tripLocations']);

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



Route::get('/trips/{tripId}/seats', [TicketController::class, 'availableSeats']);

Route::post('/trips/{tripId}/book', [TicketController::class, 'book']);

Route::get('/trips/{tripId}/tickets', [TicketController::class, 'tripTickets']);

Route::get('/trips-with-tickets', [TicketController::class, 'allTripsWithTickets']);


  Route::put('/tickets/{ticketId}/assign', [TicketController::class, 'assignBusAndDriver']);

    // ✅ Assign bus & driver to multiple tickets at once
    Route::put('assign-bulk', [TicketController::class, 'bulkAssignBusAndDriver']);


    Route::post('/tickets/{ticketId}/mark-paid', [TicketController::class, 'markAsPaid']);

    // routes/api.php
use App\Http\Controllers\RatingController;

Route::get('ratings', [RatingController::class, 'getAll']);
Route::post('ratings', [RatingController::class, 'create']);
Route::put('ratings/{id}', [RatingController::class, 'update']);
Route::delete('ratings/{id}', [RatingController::class, 'delete']);



Route::post('/trips/{trip}/rate', [RatingController::class, 'store']);
Route::get('/trips/{trip}/ratings-summary', [RatingController::class, 'summary']);
Route::get('/trips/{trip}/ratings-count', [RatingController::class, 'count']);

Route::get('/trips/{trip}/ratings-breakdown', [RatingController::class, 'breakdown']);
Route::get('/trips/{trip}/ratings-total', [RatingController::class, 'totalScore']);


// TO:
Route::get('/payment/success', [TicketController::class, 'paymentSuccess']);



Route::post('/tickets/{ticketId}/cancel', [TicketController::class, 'cancelTicket']);
Route::post('/tickets/arrived', [TicketController::class, 'markAsArrived']);

Route::get('/tickets/{ticket}', [TicketController::class, 'show']);

Route::get('/trips/{tripId}/reviews', [RatingController::class, 'reviews']);


// Mark a single ticket as in_processing
Route::post('tickets/{ticketId}/processing', [TicketController::class, 'markAsProcessing']);


// Bulk mark tickets as in_processing
Route::post('tickets/bulk-processing', [TicketController::class, 'bulkMarkAsProcessing']);


 Route::post('tickets/{ticketId}/riding', [TicketController::class, 'markTicketsAsRiding']);
    // Bulk tickets
    Route::post('tickets/bulk-riding', [TicketController::class, 'markTicketsAsRiding']);

    Route::post('/generate-feedback-link', [TicketController::class, 'generateFeedbackLink']);


    Route::get('/coupons', [TicketController::class, 'getCoupons']);


Route::post('/tickets/{ticketId}/mark-payment-inprocessing', [TicketController::class, 'markPaymentAsProcessing']);

use App\Http\Controllers\ChalanController;

Route::post('/chalans', [ChalanController::class, 'create']); // create chalan
Route::get('/chalans', [ChalanController::class, 'index']);   // list all
Route::get('/chalans/{id}', [ChalanController::class, 'show']);
Route::put('/chalans/{id}', [ChalanController::class, 'update']);


Route::delete('/chalans/{id}', [ChalanController::class, 'destroy']);





    Route::post('/trips', [TripController::class, 'store']);

    Route::get('/tickets', [TicketController::class, 'index']);
Route::post('/tickets', [TicketController::class, 'store']);
Route::get('/tickets/{id}', [TicketController::class, 'show']);
Route::put('/tickets/{id}', [TicketController::class, 'update']);
Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
Route::get('/cleaners', [CleanerController::class, 'getCleaners']);



Route::post('/tickets/{ticketId}/mark-unpaid', [TicketController::class, 'markPaymentAsUnpaid']);


Route::prefix('attempts')->group(function () {
    Route::get('/', [PaymentAttemptController::class, 'index']);           // fetch all attempts
    Route::get('/{ticket}', [PaymentAttemptController::class, 'show']);    // single attempt
    Route::put('/{ticket}/mark-paid', [PaymentAttemptController::class, 'markPaid']);
    Route::put('/{ticket}', [PaymentAttemptController::class, 'update']);
    Route::delete('/{ticket}', [PaymentAttemptController::class, 'destroy']);
});

Route::post('/tickets/{ticketId}/cancel-seats', [TicketController::class, 'cancelSeats']);

Route::post('/trips/{tripId}/lock-seats', [TicketController::class, 'lockSeats']);
Route::post('/trips/{tripId}/unlock-seats', [TicketController::class, 'unlockSeats']);