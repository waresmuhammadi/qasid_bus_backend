<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketBookedMail;

class PaymentAttemptController extends Controller
{
    // 🔹 LIST ALL ATTEMPTS
    public function index()
    {
        return response()->json(
            PaymentAttempt::latest()->get()
        );
    }

    // 🔹 SHOW ONE ATTEMPT
    public function show($id)
    {
        $attempt = PaymentAttempt::find($id);

        if (!$attempt) {
            return response()->json([
                'message' => 'Payment attempt not found'
            ], 404);
        }

        return response()->json($attempt);
    }

    // 🔹 MARK ATTEMPT AS PAID
    public function markPaid($id)
    {
        $attempt = PaymentAttempt::find($id);

        if (!$attempt) {
            return response()->json([
                'message' => 'Payment attempt not found'
            ], 404);
        }

        if ($attempt->status === 'paid') {
            return response()->json([
                'message' => 'Attempt already paid'
            ], 400);
        }

        // ✅ CREATE REAL TICKET
        $trip = Trip::findOrFail($attempt->trip_id);

        $ticket = Ticket::create([
            'trip_id'         => $attempt->trip_id,
            'ticket_number'   => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(uniqid()),
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
            'from_website'    => $attempt->from_website,
        ]);

        // ✅ UPDATE ATTEMPT
        $attempt->update([
            'status' => 'paid'
        ]);

        // ✅ SEND EMAIL (SAFE)
        try {
            Mail::to($ticket->email)->send(new TicketBookedMail($ticket, $trip));
        } catch (\Throwable $e) {
            \Log::error('EMAIL FAILED (markPaid)', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id
            ]);
        }

        return response()->json([
            'message' => 'Payment attempt marked as PAID',
            'attempt' => $attempt,
            'ticket'  => $ticket
        ]);
    }

    // 🔹 DELETE ATTEMPT
    public function destroy($id)
    {
        $attempt = PaymentAttempt::find($id);

        if (!$attempt) {
            return response()->json([
                'message' => 'Payment attempt not found'
            ], 404);
        }

        $attempt->delete();

        return response()->json([
            'message' => 'Payment attempt deleted'
        ]);
    }
}
