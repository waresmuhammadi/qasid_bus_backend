<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Ticket;

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
        'departure_date' => 'sometimes'
    ]);

    $trip = Trip::find($tripId);
    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    $departureDate = null;

    if ($trip->all_days) {
        // ✅ Daily trips → require user-provided date
        if (!$request->has('departure_date')) {
            return response()->json([
                'message' => 'Departure date is required for daily available trips'
            ], 400);
        }
        $departureDate = $request->departure_date;
    } else {
        // ✅ Fixed-date trips
        $departureDate = $trip->departure_date;

        // If user provided a departure_date and it does not match → error
        if ($request->has('departure_date') && $request->departure_date != $trip->departure_date) {
            return response()->json([
                'message' => 'This trip has a fixed departure date. You must select ' . $trip->departure_date
            ], 400);
        }
    }

    // Check for already booked seats for this specific departure date
    $alreadyBooked = Ticket::where('trip_id', $tripId)
        ->where('departure_date', $departureDate)
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

    $paymentStatus = $request->payment_method === 'hessabpay' ? 'paid' : 'unpaid';

    $ticket = Ticket::create([
        'trip_id'        => $tripId,
        'departure_date' => $departureDate,
        'seat_numbers'   => $request->seat_numbers,
        'name'           => $request->name,
        'last_name'      => $request->last_name,
        'phone'          => $request->phone,
        'email'          => $request->email ?? null,
        'payment_method' => $request->payment_method,
        'payment_status' => $paymentStatus,
        'bus_type'       => $request->bus_type,
    ]);

    return response()->json([
        'message'             => 'Tickets booked successfully',
        'customer_name'       => $ticket->name,
        'customer_last_name'  => $ticket->last_name,
        'customer_full_name'  => $ticket->name . ' ' . $ticket->last_name,
        'phone'               => $ticket->phone,
        'payment_method'      => $ticket->payment_method,
        'payment_status'      => $ticket->payment_status,
        'booked_seats'        => $ticket->seat_numbers,
        'bus_type'            => $ticket->bus_type,
        'departure_date'      => $ticket->departure_date,
        'total_seats_booked'  => count($ticket->seat_numbers),
        'trip_details'        => [
            'from'           => $trip->from,
            'to'             => $trip->to,
            'departure_date' => $trip->departure_date,
            'departure_time' => $trip->departure_time,
        ]
    ], 201);
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
}