<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Ticket;
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

    // ✅ FIX: get booked seats correctly
   $bookedSeats = Ticket::where('trip_id', $tripId)
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
        'departure_date' => 'sometimes'
    ]);


// ✅ Generate unique ticket number
$ticketNumber = $this->generateUniqueTicketNumber();

    $trip = Trip::find($tripId);
    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    // ✅ Handle departure date rules
    $departureDate = $trip->all_days
        ? $request->departure_date
        : $trip->departure_date;

    if ($trip->all_days && !$departureDate) {
        return response()->json(['message' => 'Departure date is required'], 400);
    }

    // ✅ Check conflicts
   $alreadyBooked = Ticket::where('trip_id', $tripId)
    ->where('departure_date', $departureDate)
    ->where('status', '!=', 'cancelled') // ✅ ignore cancelled tickets
    ->where('payment_status', '!=', 'failed') // ✅ ignore failed payments
    ->pluck('seat_numbers')
    ->flatten()
    ->toArray();


    $conflicts = array_intersect($alreadyBooked, $request->seat_numbers);
    if (!empty($conflicts)) {
        return response()->json([
            'message'   => 'Some seats are already booked for this date',
            'conflicts' => $conflicts
        ], 409);
    }

    // ✅ Default status
    $paymentStatus = $request->payment_method === 'hessabpay' ? 'pending' : 'unpaid';

    // ✅ Create Ticket (not yet fully paid if hessabpay)
    $ticketNumber = $this->generateUniqueTicketNumber();
    $ticket = Ticket::create([
        'trip_id'        => $tripId,
        'ticket_number'   => $ticketNumber,
        'departure_date' => $departureDate,
        'seat_numbers'   => $request->seat_numbers,
        'name'           => $request->name,
        'last_name'      => $request->last_name,
        'phone'          => $request->phone,
        'email'          => $request->email ?? null,
        'address'        => $request->address ?? null,
        'payment_method' => $request->payment_method,
        'payment_status' => $paymentStatus,
        'bus_type'       => $request->bus_type,
    ]);

   try {
    Mail::to('arianniy800@gmail.com')->send(new TicketBookedMail($ticket, $trip));
} catch (\Exception $e) {
    \Log::error('Mail sending failed: '.$e->getMessage());
}


    // ✅ If HessabPay → create session
    if ($request->payment_method === 'hessabpay') {
        $apiKey = env('HESABPAY_API_KEY');

      $items = [
    [
        'id' => 'TKT-' . $ticket->id,  // unique ID per ticket
        'name' => "Bus Ticket - Trip #{$trip->id}",
        'price' => $trip->price ?? 100, 
        'quantity' => 1,
    ]
];

$response = Http::withHeaders([
    'Authorization' => 'API-KEY ' . env('HESABPAY_API_KEY'),
    'Accept'        => 'application/json',
])->post(env('HESABPAY_BASE_URL') . '/payment/create-session', [
    'email' => $ticket->email,
    'items' => $items,
    'currency' => 'AFN',
    'redirect_success_url' => env('HESABPAY_SUCCESS_URL') . "/{$ticket->id}",
    'redirect_failure_url' => env('HESABPAY_FAILURE_URL') . "/{$ticket->id}",
]);



        if ($response->successful()) {
            $data = $response->json();

            return response()->json([
                'message' => 'Ticket created, redirect to payment',
                'ticket'  => $ticket,
                'payment' => $data   // contains checkout_url
            ], 201);
        } else {
            Log::error('HesabPay Create Session Error:', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return response()->json([
                'error' => 'Payment session failed',
                'ticket' => $ticket,
                'response' => $response->body()
            ], 500);
        }
    }

    // ✅ Doorpay (cash)
    return response()->json([
        'message'             => 'Tickets booked successfully',
        'ticket'              => $ticket,
        'trip_details'        => $trip
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



}