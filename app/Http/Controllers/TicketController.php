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
    // Show available seats for a specific bus type in a trip
    public function availableSeats(Request $request, $tripId)
{
    $request->validate([
        'bus_type' => 'required|in:VIP,580',
    ]);

    $busType = $request->bus_type;

    $trip = Trip::find($tripId);

    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    if (!in_array($busType, $trip->bus_type)) {
        return response()->json(['message' => 'This bus type is not available for this trip'], 400);
    }

    // ✅ FIXED: Get booked seats for THIS bus type only
    $bookedSeats = Ticket::where('trip_id', $tripId)
        ->where('bus_type', $busType) // ← CRITICAL: Add this line
        ->where('status', '!=', 'cancelled')
        ->pluck('seat_numbers')
        ->flatten()
        ->toArray();

    $seatRange = $this->getSeatRangeForBusType($trip->bus_type, $busType);

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
        'seats'             => $seats,
        'total_capacity'    => $capacity,
    ]);
}
    // Helper method to get seat range for a specific bus type
   private function getSeatRangeForBusType($tripBusTypes, $selectedBusType)
{
    $capacity = ($selectedBusType === 'VIP') ? 36 : 51;

    return ['start' => 1, 'end' => $capacity];
}

public function book(Request $request, $tripId)
{
    $request->validate([
        'seat_numbers'   => 'required|array',
        'seat_numbers.*' => 'integer',
        'name'           => 'required|string|max:255',
        'last_name'      => 'required|string|max:255',
        'phone'          => 'required|string|max:20',
        'payment_method' => 'required|in:hessabpay,doorpay',
        'bus_type'       => 'required|in:VIP,580',
        'email'          => 'required_if:payment_method,hessabpay|email',
        'address'        => 'required_if:payment_method,doorpay|string|max:500',
        'departure_date' => 'sometimes|date',
        'coupon_code'    => 'nullable|string|exists:coupons,code'
    ]);

    $ticketNumber = $this->generateUniqueTicketNumber();

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
        ->where('payment_status', '!=', 'failed')
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

    // ✅ CLEAR PAYMENT STATUS LOGIC - ONLY 3 VALUES: paid, unpaid, in_processing
    $paymentStatus = $request->payment_method === 'hessabpay' ? 'paid' : 'in_processing';

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

    // ✅ Create ticket - REMOVE THE STATUS FIELD COMPLETELY
    $ticket = Ticket::create([
        'trip_id'         => $tripId,
        'ticket_number'   => $ticketNumber,
        'departure_date'  => $departureDate,
        'seat_numbers'    => $request->seat_numbers,
        'name'            => $request->name,
        'last_name'       => $request->last_name,
        'phone'           => $request->phone,
        'email'           => $request->email ?? null,
        'address'         => $request->address ?? null,
        'payment_method'  => $request->payment_method,
        'payment_status'  => $paymentStatus, // ✅ paid OR in_processing ONLY
        'bus_type'        => $request->bus_type,
        'coupon_code'     => $couponCode,
        'discount_amount' => $discountAmount,
        'final_price'     => $finalPrice,
        // ✅ REMOVED: 'status' => 'booked' - Let the database use its default value
    ]);

    // Capture website info
    $ticket->from_website = $request->from_website
        ?? $request->header('Origin')
        ?? $request->header('Referer')
        ?? request()->getSchemeAndHttpHost();
    $ticket->save();

    // Send email
    try {
        Mail::to('arianniy800@gmail.com')->send(new TicketBookedMail($ticket, $trip));
    } catch (\Exception $e) {
        \Log::error('Mail sending failed: ' . $e->getMessage());
    }

    // If HessabPay → create session
    if ($request->payment_method === 'hessabpay') {
        $items = [
            [
                'id'       => 'TKT-' . $ticket->id,
                'name'     => "Bus Ticket - Trip #{$trip->id}",
                'price'    => $ticket->final_price,
                'quantity' => 1,
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'API-KEY ' . env('HESABPAY_API_KEY'),
            'Accept'        => 'application/json',
        ])->post(env('HESABPAY_BASE_URL') . '/payment/create-session', [
            'email'               => $ticket->email,
            'items'               => $items,
            'currency'            => 'AFN',
            'redirect_success_url'=> env('HESABPAY_SUCCESS_URL') . "/{$ticket->id}",
            'redirect_failure_url'=> env('HESABPAY_FAILURE_URL') . "/{$ticket->id}",
        ]);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Ticket created, redirect to payment',
                'ticket'  => $ticket,
                'payment' => $response->json()
            ], 201);
        } else {
            \Log::error('HesabPay Create Session Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'error'    => 'Payment session failed',
                'ticket'   => $ticket,
                'response' => $response->body()
            ], 500);
        }
    }

    // Doorpay response
    return response()->json([
        'message'       => 'Tickets booked successfully - Payment in processing',
        'ticket'        => $ticket,
        'final_price'   => $ticket->final_price,
        'trip_details'  => $trip,
        'payment_status'=> $ticket->payment_status // This will be 'in_processing'
    ], 201);
}



public function paymentSuccess(Request $request, $ticketId)
{
    $ticket = Ticket::find($ticketId);
    if (!$ticket) {
        return response()->json(['error' => 'Ticket not found'], 404);
    }

    // Decode HessabPay query data
    $dataParam = $request->query('data');
    $decoded = urldecode($dataParam);
    $data = json_decode($decoded, true);

    if (json_last_error() !== JSON_ERROR_NONE || !$data) {
        return redirect(env('FRONTEND_URL') . '/payment-error?message=Invalid+data');
    }

    $transactionId = $data['transaction_id'] ?? null;
    $success = $data['success'] ?? false;

    if ($success && $transactionId) {
        // ✅ Update ticket payment status
        $ticket->update([
            'payment_status' => 'paid',
            'transaction_id' => $transactionId,
        ]);

        // ✅ Get the company API URL from the trip
        $trip = Trip::find($ticket->trip_id);
        $companyApiUrl = $trip->company_api_url ?? env('APP_URL'); // Fallback to your main URL

        // ✅ CRITICAL FIX: Include company_api_url in redirect
        return redirect(
            env('FRONTEND_URL') . 
            "/done?" . 
            "ticket_id={$ticketId}&" .
            "transaction_id={$transactionId}&" .
            "company_api_url=" . urlencode($companyApiUrl)
        );
    }

    $ticket->update(['payment_status' => 'failed']);
    return redirect(env('FRONTEND_URL') . "/payment-error?ticket_id={$ticketId}");
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
            'bus_id'    => 'required|exists:buses,id',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        $ticket->bus_id    = $request->bus_id;
        $ticket->driver_id = $request->driver_id;
        $ticket->save();

        return response()->json([
            'message' => 'Bus and driver assigned successfully to ticket!',
            'ticket'  => $ticket->load(['bus', 'driver'])
        ]);
    }

    public function bulkAssignBusAndDriver(Request $request)
    {
        $request->validate([
            'ticket_ids'   => 'required|array',
            'ticket_ids.*' => 'exists:tickets,id',
            'bus_id'       => 'required|exists:buses,id',
            'driver_id'    => 'required|exists:drivers,id',
        ]);

        Ticket::whereIn('id', $request->ticket_ids)
            ->update([
                'bus_id'    => $request->bus_id,
                'driver_id' => $request->driver_id,
            ]);

        return response()->json([
            'message' => 'Bus and driver assigned to selected tickets successfully!',
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


}