<?php

namespace App\Http\Controllers;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Ticket;
use App\Models\Coupon;
use App\Models\TempBooking;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentAttempt;
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


// ------------------- BOOK METHOD -------------------


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
        'province'       => 'nullable|string|max:255',
        'father_name'    => 'nullable|string|max:255',
        'departure_date' => 'sometimes|date',
        'coupon_code'    => 'nullable|string|exists:coupons,code'
    ]);

    $trip = Trip::findOrFail($tripId);

    $departureDate = $trip->all_days ? $request->departure_date : $trip->departure_date;
    if ($trip->all_days && !$departureDate) {
        return response()->json(['message' => 'Departure date is required'], 400);
    }

    // Seat conflict check (paid or in_processing only)
    $bookedSeats = Ticket::where('trip_id', $tripId)
        ->where('bus_type', $request->bus_type)
        ->where('departure_date', $departureDate)
        ->whereIn('payment_status', ['paid','in_processing'])
        ->pluck('seat_numbers')
        ->flatten()
        ->toArray();

    if (array_intersect($bookedSeats, $request->seat_numbers)) {
        return response()->json(['message' => 'Seats already taken'], 409);
    }

    // Price calculation
    $basePrice = $trip->prices[$request->bus_type] ?? 0;
    $seatCount = count($request->seat_numbers);

    $coupon = \App\Models\Coupon::where('code', $request->coupon_code)->first();
    $discount = $coupon ? $coupon->amount : 0;

    $finalPrice = max(($basePrice * $seatCount) - $discount, 0);

    // Determine from_website
    // Determine the origin / frontend URL
$fromWebsite = $request->from_website ?? $request->header('Origin') ?? $request->header('Referer') ?? request()->getSchemeAndHttpHost();

    // Auto-paid check
    $paymentStatus = 'in_processing';
    $autoPaidDomains = explode(',', env('AUTO_PAID_DOMAINS', ''));
    foreach ($autoPaidDomains as $domain) {
        if (!empty($domain) && str_contains($fromWebsite, trim($domain))) {
            $paymentStatus = 'paid';
            break;
        }
    }

    // ===== DOORPAY: immediate ticket creation =====
    if ($request->payment_method === 'doorpay') {
        $ticket = Ticket::create([
            'trip_id'         => $tripId,
            'ticket_number'   => $this->generateUniqueTicketNumber(),
            'departure_date'  => $departureDate,
            'seat_numbers'    => $request->seat_numbers,
            'name'            => $request->name,
            'phone'           => $request->phone,
            'email'           => $request->email,
            'address'         => $request->address ?? null,
            'bus_type'        => $request->bus_type,
            'payment_method'  => 'doorpay',
            'payment_status'  => $paymentStatus,
            'status'          => 'stopped',
            'province'        => $request->province ?? null,
            'father_name'     => $request->father_name ?? null,
            'coupon_code'     => $request->coupon_code,
            'discount_amount' => $discount,
            'final_price'     => $finalPrice,
            'from_website'    => $fromWebsite,
        ]);

        try {
            if (!empty($ticket->email)) {
                Mail::to($ticket->email)->send(new TicketBookedMail($ticket, $trip));
            }
        } catch (\Throwable $e) {
            \Log::error('DOORPAY EMAIL FAILED', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $paymentStatus === 'paid' ? 'Tickets booked successfully - Payment automatically marked as PAID!' : 'Tickets booked successfully - Payment in processing',
            'ticket' => $ticket,
            'final_price' => $finalPrice,
            'trip_details' => $trip,
            'payment_status'=> $ticket->payment_status,
        ], 201);
    }

    // ===== HESSABPAY: create attempt first =====
    $attempt = PaymentAttempt::create([
        'trip_id'         => $tripId,
        'seat_numbers'    => $request->seat_numbers,
        'name'            => $request->name,
        'phone'           => $request->phone,
        'email'           => $request->email,
        'bus_type'        => $request->bus_type,
        'departure_date'  => $departureDate,
        'coupon_code'     => $request->coupon_code,
        'discount_amount' => $discount,
        'final_price'     => $finalPrice,
        'status'          => 'pending',
        'from_website'    => $fromWebsite,
    ]);

    // Create HessabPay session
    $items = [[
        'id' => 'attempt-' . $attempt->id,
        'name' => "Bus Ticket",
        'price' => $finalPrice,
        'quantity' => 1,
    ]];

    $response = Http::withHeaders([
        'Authorization' => 'API-KEY ' . env('HESABPAY_API_KEY'),
    ])->post(env('HESABPAY_BASE_URL') . '/payment/create-session', [
        'email' => $attempt->email,
        'items' => $items,
        'currency' => 'AFN',
        'redirect_success_url' => env('HESABPAY_SUCCESS_URL') . "?attempt_id={$attempt->id}",
        'redirect_failure_url' => env('HESABPAY_FAILURE_URL') . "?attempt_id={$attempt->id}",
    ]);

    return response()->json([
        'message' => 'Booking saved. Redirect to payment.',
        'ticket_attempt_id' => $attempt->id,
        'payment' => $response->json(),
    ], 200);
}

public function paymentSuccess(Request $request)
{
    try {
        $fullUrl = $request->fullUrl();
        \Log::info('RAW URL FROM HESSABPAY: ' . $fullUrl);

        if (!preg_match('/attempt_id=(\d+)/', $fullUrl, $m)) {
            throw new \Exception('attempt_id not found');
        }

        $attemptId = $m[1];
        $attempt = \App\Models\PaymentAttempt::findOrFail($attemptId);

        if ($attempt->status === 'paid') {
            return redirect(env('FRONTEND_URL') . '/done?ticket_id=' . $attempt->ticket_id);
        }

        // Create ticket after successful payment
        $ticket = Ticket::create([
            'trip_id'         => $attempt->trip_id,
            'ticket_number'   => $this->generateUniqueTicketNumber(),
            'seat_numbers'    => $attempt->seat_numbers,
            'name'            => $attempt->name,
            'phone'           => $attempt->phone,
            'email'           => $attempt->email,
            'bus_type'        => $attempt->bus_type,
            'departure_date'  => $attempt->departure_date,
            'payment_method'  => 'hessabpay',
            'payment_status'  => 'paid',
            'status'          => 'stopped',
            'final_price'     => $attempt->final_price,
            'coupon_code'     => $attempt->coupon_code,
            'discount_amount'=> $attempt->discount_amount,
            'from_website'    => $attempt->from_website, // ✅ from_website preserved
        ]);

        $attempt->update([
            'status' => 'paid',
            'ticket_id' => $ticket->id
        ]);

        // Send email
        $trip = Trip::find($ticket->trip_id);
        if (!empty($ticket->email)) {
            try {
                Mail::to($ticket->email)->send(new TicketBookedMail($ticket, $trip));
            } catch (\Throwable $mailError) {
                \Log::error('EMAIL FAILED', [
                    'ticket_id' => $ticket->id,
                    'error' => $mailError->getMessage(),
                ]);
            }
        }

        // Redirect frontend
        $redirectUrl = env('FRONTEND_URL') . "/done" .
            "?ticket_id=" . $ticket->id .
            "&app_url=" . urlencode(env('APP_URL')) .
            "&company_name=" . urlencode(env('COMPANY_NAME', 'وردک بابا')) .
            "&company_phone=" . urlencode(env('COMPANY_PHONE', '07888888'));

        return redirect($redirectUrl);

    } catch (\Throwable $e) {
        \Log::error('PAYMENT ERROR: ' . $e->getMessage());
        return redirect(env('FRONTEND_URL') . '/payment-error');
    }
}




public function paymentFailure(Request $request)
{
    $attempt = PaymentAttempt::find($request->attempt_id);

    if ($attempt) {
        $attempt->update(['status' => 'failed']);
    }

    return redirect(env('FRONTEND_URL') . '/payment-error');
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



