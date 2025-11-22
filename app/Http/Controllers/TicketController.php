<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Ticket;
use App\Models\Coupon;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Mail;
use App\Mail\TicketBookedMail;


class TicketController extends Controller
{
 public function availableSeats(Request $request, $tripId)
{
    $request->validate([
        'bus_type'       => 'required|in:VIP,580',
        'departure_date' => 'nullable|date'
    ]);

    $busType = $request->bus_type;
    $trip = Trip::find($tripId);

    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    // ✅ DEBUG: Check what bus types the trip actually has
    \Log::info('Trip Debug:', [
        'trip_id' => $tripId,
        'requested_bus_type' => $busType,
        'trip_bus_types' => $trip->bus_type,
        'has_vip' => in_array('VIP', $trip->bus_type),
        'trip_data' => $trip->toArray()
    ]);

    // Check if the bus type exists for this trip
    if (!in_array($busType, $trip->bus_type)) {
        return response()->json([
            'message' => 'This bus type is not available for this trip',
            'available_bus_types' => $trip->bus_type,
            'requested_bus_type' => $busType
        ], 400);
    }
    

    // Determine which date to check
    $departureDate = $trip->all_days
        ? ($request->departure_date ?? now()->toDateString())
        : $trip->departure_date;

    // Get booked seats
    $bookedSeats = Ticket::where('trip_id', $tripId)
        ->where('bus_type', $busType)
        ->where('departure_date', $departureDate)
        ->where('status', '!=', 'cancelled')
        ->where(function($query) {
            $query->where('payment_status', 'paid')
                  ->orWhere('payment_status', 'in_processing')
                  ->orWhere('payment_status', 'unpaid');
        })
        ->pluck('seat_numbers')
        ->flatten()
        ->toArray();

    // ✅ USE ADDITIONAL CAPACITY
    $seatRange = $this->getSeatRangeForBusType(
        $trip->bus_type, 
        $busType, 
        $trip->additional_capacity
    );

    if (!$seatRange) {
        return response()->json(['message' => 'Invalid bus type configuration'], 400);
    }

    $startSeat = $seatRange['start'];
    $endSeat   = $seatRange['end'];
    $capacity  = $endSeat - $startSeat + 1;

    $seats = [];
    for ($i = $startSeat; $i <= $endSeat; $i++) {
        $seats[] = [
            'seat_number' => $i,
            'status'      => in_array($i, $bookedSeats) ? 'booked' : 'free'
        ];
    }

    return response()->json([
        'trip'              => $trip,
        'selected_bus_type' => $busType,
        'departure_date'    => $departureDate,
        'seats'             => $seats,
        'base_capacity'     => $seatRange['base_capacity'],
        'additional_capacity' => $seatRange['additional_capacity'],
        'total_capacity'    => $seatRange['total_capacity'],
        'capacity_info'     => "Base: {$seatRange['base_capacity']} + Additional: {$seatRange['additional_capacity']} = Total: {$seatRange['total_capacity']}"
    ]);
}

    // Helper method to get seat range for a specific bus type
// In TicketController availableSeats method, update the seat range calculation:
private function getSeatRangeForBusType($tripBusTypes, $selectedBusType, $additionalCapacity = [])
{
    $baseCapacity = ($selectedBusType === 'VIP') ? 35 : 49;
    
    // Get additional capacity for the specific bus type
    $additionalForBusType = $additionalCapacity[$selectedBusType] ?? 0;
    $totalCapacity = $baseCapacity + $additionalForBusType;

    return [
        'start' => 1, 
        'end' => $totalCapacity,
        'base_capacity' => $baseCapacity,
        'additional_capacity' => $additionalForBusType,
        'total_capacity' => $totalCapacity
    ];
}

public function book(Request $request, $tripId)
{
    $request->validate([
        'seat_numbers'   => 'required|array',
        'seat_numbers.*' => 'integer',
        'name'           => 'required|string|max:255',
        'phone'          => 'required|string|max:20',
        'payment_method' => 'required|in:hessabpay,doorpay',
        'bus_type'       => 'required|in:VIP,580',
        'email'          => 'required_if:payment_method,hessabpay|email',
        'address'        => 'required_if:payment_method,doorpay|string|max:500',
        'province'      => 'nullable|string|max:255',
        'father_name'   => 'nullable|string|max:255',  
        'departure_date' => 'sometimes|date',
        'coupon_code'    => 'nullable|string|exists:coupons,code'
    ]);

    $trip = Trip::find($tripId);
    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    // Determine departure date
    $departureDate = $trip->all_days
        ? $request->departure_date
        : $trip->departure_date;

    if ($trip->all_days && !$departureDate) {
        return response()->json(['message' => 'Departure date is required'], 400);
    }

    // Check for seat conflicts
    $alreadyBooked = Ticket::where('trip_id', $tripId)
        ->where('bus_type', $request->bus_type)
        ->where('departure_date', $departureDate)
        ->where('status', '!=', 'cancelled')
        ->where(function($query) {
            $query->where('payment_status', 'paid')
                  ->orWhere('payment_status', 'in_processing');
        })
        ->pluck('seat_numbers')
        ->flatten()
        ->toArray();

    $conflicts = array_intersect($alreadyBooked, $request->seat_numbers);
    if (!empty($conflicts)) {
        return response()->json([
            'message'   => 'Some seats are already booked for this date and bus type',
            'conflicts' => $conflicts
        ], 409);
    }

    // Base price calculation
    $basePrice = $trip->prices[$request->bus_type] ?? 0;
    $seatCount = count($request->seat_numbers);

    // Handle coupon
    $couponCode = $request->coupon_code;
    $discountAmount = 0;
    if ($couponCode) {
        $coupon = \App\Models\Coupon::where('code', $couponCode)->first();
        if ($coupon) {
            $discountAmount = $coupon->amount;
        }
    }

    $finalPrice = max(($basePrice * $seatCount) - $discountAmount, 0);

    // ✅ DETERMINE PAYMENT STATUS BASED ON FROM_WEBSITE
    // ✅ DETERMINE PAYMENT STATUS BASED ON FROM_WEBSITE
// ✅ DETERMINE PAYMENT STATUS BASED ON FROM_WEBSITE
$fromWebsite = $request->from_website
    ?? $request->header('Origin')
    ?? $request->header('Referer')
    ?? request()->getSchemeAndHttpHost();

// 🎯 AUTO-PAID FOR DASHBOARD DOMAINS
$paymentStatus = 'in_processing';

$autoPaidDomains = explode(',', env('AUTO_PAID_DOMAINS', ''));

foreach ($autoPaidDomains as $domain) {
    $domain = trim($domain);
    if (!empty($domain) && str_contains($fromWebsite, $domain)) {
        $paymentStatus = 'paid';
        break;
    }
}

// 🎯 DEBUG: Log what's happening
\Log::info('PAYMENT STATUS DETERMINATION:', [
    'from_website' => $fromWebsite,
    'payment_status' => $paymentStatus,
    'payment_method' => $request->payment_method,
    'matched_dashboard' => $paymentStatus === 'paid' ? 'YES' : 'NO'
]);

    // ✅ FOR HESSABPAY: DON'T CREATE TICKET HERE - JUST CREATE PAYMENT SESSION
    if ($request->payment_method === 'hessabpay') {
        // Create temporary booking data (but NOT in database)
        $bookingData = [
            'trip_id' => $tripId,
            'seat_numbers' => $request->seat_numbers,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'bus_type' => $request->bus_type,
            'departure_date' => $departureDate,
            'coupon_code' => $couponCode,
            'discount_amount' => $discountAmount,
            'province'     => $request->province ?? null,
            'father_name'  => $request->father_name ?? null,
            'final_price' => $finalPrice,
            'from_website' => $fromWebsite,
        ];

        // Create payment session with booking data
        $items = [
            [
                'id'       => 'temp-booking-' . uniqid(),
                'name'     => "Bus Ticket - Trip #{$trip->id}",
                'price'    => $finalPrice,
                'quantity' => 1,
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'API-KEY ' . env('HESABPAY_API_KEY'),
            'Accept'        => 'application/json',
        ])->post(env('HESABPAY_BASE_URL') . '/payment/create-session', [
            'email'               => $request->email,
            'items'               => $items,
            'currency'            => 'AFN',
            'redirect_success_url'=> env('HESABPAY_SUCCESS_URL') . "?booking_data=" . urlencode(json_encode($bookingData)),
            'redirect_failure_url'=> env('HESABPAY_FAILURE_URL'),
        ]);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Redirect to payment',
                'payment' => $response->json()
            ], 200);
        } else {
            \Log::error('HesabPay Create Session Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'error'    => 'Payment session failed',
                'response' => $response->body()
            ], 500);
        }
    }

    // ✅ FOR DOORPAY: Create ticket immediately since it's manual payment
    if ($request->payment_method === 'doorpay') {
        $ticketNumber = $this->generateUniqueTicketNumber();
        
        $ticket = Ticket::create([
            'trip_id'         => $tripId,
            'ticket_number'   => $ticketNumber,
            'departure_date'  => $departureDate,
            'seat_numbers'    => $request->seat_numbers,
            'name'            => $request->name,
            'phone'           => $request->phone,
            'email'           => $request->email ?? null,
            'address'         => $request->address ?? null,
            'payment_method'  => $request->payment_method,
            'payment_status'  => $paymentStatus, // 🎯 THIS SHOULD BE 'paid' FOR LOCALHOST!
            'province'        => $request->province ?? null,
            'father_name'     => $request->father_name ?? null,
            'bus_type'        => $request->bus_type,
            'coupon_code'     => $couponCode,
            'discount_amount' => $discountAmount,
            'final_price'     => $finalPrice,
        ]);

        $ticket->from_website = $fromWebsite;
        $ticket->save();

        // 🎯 DEBUG: Log the final ticket status
        \Log::info('DOORPAY TICKET CREATED:', [
            'ticket_id' => $ticket->id,
            'payment_status' => $ticket->payment_status,
            'from_website' => $ticket->from_website,
            'is_paid' => $ticket->payment_status === 'paid'
        ]);

        return response()->json([
            'message'       => $paymentStatus === 'paid' 
                ? 'Tickets booked successfully - Payment automatically marked as PAID! 🎉' 
                : 'Tickets booked successfully - Payment in processing',
            'ticket'        => $ticket,
            'final_price'   => $ticket->final_price,
            'trip_details'  => $trip,
            'payment_status'=> $ticket->payment_status,
            'auto_paid'     => $paymentStatus === 'paid' // 🎯 Flag to indicate auto-paid
        ], 201);
    }
}

public function paymentSuccess(Request $request)
{
    // Get booking data from URL parameter
    $bookingDataJson = $request->query('booking_data');
    $bookingData = json_decode(urldecode($bookingDataJson), true);

    if (!$bookingData) {
        return redirect(env('FRONTEND_URL') . '/payment-error?message=Invalid+booking+data');
    }

    // Decode HessabPay query data
    $dataParam = $request->query('data');
    $decoded = urldecode($dataParam);
    $data = json_decode($decoded, true);

    if (json_last_error() !== JSON_ERROR_NONE || !$data) {
        return redirect(env('FRONTEND_URL') . '/payment-error?message=Invalid+payment+data');
    }

    $transactionId = $data['transaction_id'] ?? null;
    $success = $data['success'] ?? false;

    if ($success && $transactionId) {
        // ✅ NOW CREATE THE TICKET ONLY AFTER SUCCESSFUL PAYMENT
        $ticketNumber = $this->generateUniqueTicketNumber();

        $ticket = Ticket::create([
            'trip_id'         => $bookingData['trip_id'],
            'ticket_number'   => $ticketNumber,
            'departure_date'  => $bookingData['departure_date'],
            'seat_numbers'    => $bookingData['seat_numbers'],
            'name'            => $bookingData['name'],
       
            'phone'           => $bookingData['phone'],
            'email'           => $bookingData['email'],
            'payment_method'  => 'hessabpay',
            'payment_status'  => 'paid', // ✅ MARK AS PAID
            'bus_type'        => $bookingData['bus_type'],
            'coupon_code'     => $bookingData['coupon_code'],
            'discount_amount' => $bookingData['discount_amount'],
            'final_price'     => $bookingData['final_price'],
            'transaction_id'  => $transactionId,
        ]);

        $ticket->from_website = $bookingData['from_website'];
        $ticket->save();

        // Send email
        try {
            $trip = Trip::find($bookingData['trip_id']);
            Mail::to('arianniy800@gmail.com')->send(new TicketBookedMail($ticket, $trip));
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
        }

        // Redirect to success page
        $trip = Trip::find($ticket->trip_id);
        $companyApiUrl = $trip->company_api_url ?? env('APP_URL');

        return redirect(
            env('FRONTEND_URL') . 
            "/done?" . 
            "ticket_id={$ticket->id}&" .
            "transaction_id={$transactionId}&" .
            "company_api_url=" . urlencode($companyApiUrl)
        );
    }

    return redirect(env('FRONTEND_URL') . '/payment-error');
}

public function paymentFailure($ticketId)
{
    $ticket = Ticket::find($ticketId);

    if ($ticket) {
        $ticket->payment_status = 'failed';
        $ticket->save();
    }

    return response()->json([
        'message' => 'Payment failed',
        'ticket'  => $ticket
    ], 400);
}




  // Mark ticket as paid manually (for doorpay)
public function markAsPaid($ticketId)
{
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    if ($ticket->payment_status === 'paid') {
        return response()->json(['message' => 'Ticket already paid'], 400);
    }

    $ticket->payment_status = 'paid';
    $ticket->save();

    return response()->json([
        'message' => 'Ticket marked as paid successfully',
        'ticket'  => $ticket
    ]);
}

public function show($id)
{
    $ticket = Ticket::with('trip')->find($id); // include trip relationship

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    $trip = $ticket->trip;

    return response()->json([
        'ticket' => $ticket,
        'trip' => $trip,   // include trip details
    ], 200);
}



    public function tripTickets($tripId)
    {
        $trip = Trip::find($tripId);

        if (!$trip) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        $tickets = Ticket::where('trip_id', $tripId)->get();

        return response()->json([
            'trip'    => $trip,
            'tickets' => $tickets
        ]);
    }

    public function allTripsWithTickets()
    {
        $trips = Trip::with('tickets')->get();

        return response()->json([
            'trips' => $trips
        ]);
    }

public function assignBusAndDriver(Request $request, $ticketId)
{
    $request->validate([
        'driver_id' => 'required|exists:drivers,id',
        'cleaner_id' => 'nullable|exists:cleaners,id',
    ]);

    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    // Get driver to extract bus_number_plate
    $driver = \App\Models\Driver::find($request->driver_id);
    
    // DEBUG: Log what we're about to update
    \Log::info("Updating ticket {$ticketId} with driver: {$request->driver_id}, bus_plate: " . ($driver->bus_number_plate ?? 'null'));
    
    // Update the ticket - use direct assignment
    $ticket->driver_id = $request->driver_id;
    $ticket->bus_number_plate = $driver->bus_number_plate ?? null;
    
    // Add cleaner if provided
    if ($request->has('cleaner_id') && $request->cleaner_id) {
        $ticket->cleaner_id = $request->cleaner_id;
    }
    
    $ticket->save(); // Use save() instead of update()

    // Reload the ticket with relationships
    $ticket->load(['driver', 'cleaner']);

    // DEBUG: Log the result
    \Log::info("Ticket after update - driver_id: {$ticket->driver_id}, bus_plate: {$ticket->bus_number_plate}");

    return response()->json([
        'message' => 'Driver and bus number plate assigned successfully to ticket!',
        'ticket'  => $ticket
    ]);
}

public function bulkAssignBusAndDriver(Request $request)
{
    $request->validate([
        'ticket_ids'   => 'required|array',
        'ticket_ids.*' => 'exists:tickets,id',
        'driver_id'    => 'required|exists:drivers,id',
        'cleaner_id'   => 'nullable|exists:cleaners,id',
    ]);

    // Get driver to extract bus_number_plate
    $driver = \App\Models\Driver::find($request->driver_id);
    
    $updateData = [
        'driver_id' => $request->driver_id,
        'bus_number_plate' => $driver->bus_number_plate ?? null,
    ];

    // Add cleaner if provided
    if ($request->has('cleaner_id') && $request->cleaner_id) {
        $updateData['cleaner_id'] = $request->cleaner_id;
    }

    // DEBUG: Log bulk update
    \Log::info("Bulk updating tickets: " . implode(', ', $request->ticket_ids));
    \Log::info("Update data: ", $updateData);

    $updatedCount = Ticket::whereIn('id', $request->ticket_ids)->update($updateData);

    // DEBUG: Log result
    \Log::info("Updated {$updatedCount} tickets");

    return response()->json([
        'message' => 'Driver and bus number plate assigned to selected tickets successfully!',
        'updated_count' => $updatedCount
    ]);
}


public function cancelTicket($ticketId)
{
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    // ✅ Mark the ticket as cancelled
    $ticket->status = 'cancelled';
    $ticket->save();

    return response()->json([
        'message' => 'Ticket cancelled successfully. Seats are now free.',
        'ticket'  => $ticket
    ]);
}


// Mark ticket(s) as arrived
public function markAsArrived(Request $request)
{
    $request->validate([
        'ticket_ids'   => 'required|array',
        'ticket_ids.*' => 'exists:tickets,id',
    ]);

    $tickets = Ticket::whereIn('id', $request->ticket_ids)->get();

    if ($tickets->isEmpty()) {
        return response()->json(['message' => 'No valid tickets found'], 404);
    }

    foreach ($tickets as $ticket) {
        $ticket->status = 'arrived';
        $ticket->save();
    }

    return response()->json([
        'message' => 'Ticket(s) marked as arrived successfully!',
        'tickets' => $tickets
    ]);
}

private function generateUniqueTicketNumber()
{
    do {
        // Example format: TKT-20251008-ABC123
        $ticketNumber = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    } while (Ticket::where('ticket_number', $ticketNumber)->exists());

    return $ticketNumber;
}

// Mark a single ticket as "in_processing"
public function markAsProcessing($ticketId)
{
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    // Check if already in processing
    if ($ticket->status === 'in_processing') {
        return response()->json(['message' => 'Ticket is already in processing'], 400);
    }

    $ticket->status = 'in_processing';
    $ticket->save();

    return response()->json([
        'message' => 'Ticket marked as in processing successfully',
        'ticket'  => $ticket
    ], 200);
}
public function bulkMarkAsProcessing(Request $request)
{
    $request->validate([
        'ticket_ids'   => 'required|array',
        'ticket_ids.*' => 'exists:tickets,id',
    ]);

    Ticket::whereIn('id', $request->ticket_ids)
        ->update(['status' => 'in_processing']);

    return response()->json([
        'message' => 'Selected tickets marked as in processing successfully!',
    ], 200);
}

// Mark ticket(s) as "riding"
public function markTicketsAsRiding(Request $request, $ticketId = null)
{
    $ticketIds = $ticketId ? [$ticketId] : $request->ticket_ids ?? null;

    if (!$ticketIds || !is_array($ticketIds) || empty($ticketIds)) {
        return response()->json(['message' => 'No tickets provided'], 400);
    }

    // Validate ticket IDs exist
    $validTickets = Ticket::whereIn('id', $ticketIds)->get();
    if ($validTickets->isEmpty()) {
        return response()->json(['message' => 'No valid tickets found'], 404);
    }

    // Update status
    Ticket::whereIn('id', $ticketIds)->update(['status' => 'riding']);

    return response()->json([
        'message' => 'Ticket(s) marked as riding successfully!',
        'tickets' => $validTickets->fresh() // return updated tickets
    ], 200);
}

public function generateFeedbackLink(Request $request)
{
    $request->validate([
        'trip_id'      => 'required|exists:trips,id',
        'company_name' => 'required|string|max:255',
        'subdomain'    => 'required|string|max:255', // now required in body
    ]);

    $trip = Trip::find($request->trip_id);

    // Generate the feedback link with subdomain from body
    $feedbackLink = env('FRONTEND_URL') 
        . "/feedbackpage?" . http_build_query([
            'trip_id'      => $trip->id,
            'company_name' => $request->company_name,
            'from'         => $trip->from ?? 'Unknown',
            'to'           => $trip->to ?? 'Unknown',
            'subdomain'    => $request->subdomain,
        ]);

    return response()->json([
        'message'       => 'Feedback link generated successfully',
        'feedback_link' => $feedbackLink
    ], 200);
}


// In your CouponController.php


public function getCoupons()
{
    // Fetch all coupons
    $coupons = Coupon::all();

    // Optionally, you can filter active coupons
    // $coupons = Coupon::where('status', 'active')->get();

    // Return as JSON for your frontend
    return response()->json([
        'success' => true,
        'data' => $coupons
    ]);
}

// ✅ Mark a ticket payment as "in_processing"
public function markPaymentAsProcessing($ticketId)
{
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    // Check if already in processing
    if ($ticket->payment_status === 'in_processing') {
        return response()->json(['message' => 'Ticket payment is already in processing'], 400);
    }

    // Update status
    $ticket->payment_status = 'in_processing';
    $ticket->save();

    return response()->json([
        'message' => 'Ticket payment marked as in processing successfully',
        'ticket'  => $ticket
    ], 200);
}


// ✅ Mark a ticket payment as "unpaid"
public function markPaymentAsUnpaid($ticketId)
{
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return response()->json(['message' => 'Ticket not found'], 404);
    }

    // Check if already unpaid
    if ($ticket->payment_status === 'unpaid') {
        return response()->json(['message' => 'Ticket payment is already unpaid'], 400);
    }

    // Update status
    $ticket->payment_status = 'unpaid';
    $ticket->save();

    return response()->json([
        'message' => 'Ticket payment marked as unpaid successfully',
        'ticket'  => $ticket
    ], 200);
}


}



