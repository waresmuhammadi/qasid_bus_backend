<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Ticket;

class TicketController extends Controller
{
    // Show available seats for a trip
    public function availableSeats($tripId)
    {
        $trip = Trip::find($tripId);

        if (!$trip) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        // Example: assume each bus has 40 seats
        $totalSeats = 40;
        $bookedSeats = Ticket::where('trip_id', $tripId)->pluck('seat_number')->toArray();

        $availableSeats = [];
        for ($i = 1; $i <= $totalSeats; $i++) {
            $availableSeats[] = [
                'seat_number' => $i,
                'status' => in_array($i, $bookedSeats) ? 'booked' : 'free'
            ];
        }

        return response()->json([
            'trip' => $trip,
            'seats' => $availableSeats
        ]);
    }

    // Book a ticket
   public function book(Request $request, $tripId)
{
    $request->validate([
        'seat_number' => 'required|integer',
        'name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'payment_method' => 'required|in:hessappay,doorpay', // ✅ validate two methods
    ]);

    $trip = Trip::find($tripId);

    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    // Check if seat already booked
    $exists = Ticket::where('trip_id', $tripId)
        ->where('seat_number', $request->seat_number)
        ->exists();

    if ($exists) {
        return response()->json(['message' => 'Seat already booked'], 400);
    }

    $ticket = Ticket::create([
        'trip_id' => $tripId,
        'seat_number' => $request->seat_number,
        'name' => $request->name,
        'last_name' => $request->last_name,
        'phone' => $request->phone,
        'payment_method' => $request->payment_method,
    ]);

    return response()->json([
        'message' => 'Ticket booked successfully',
        'ticket' => $ticket
    ], 201);
}

}
