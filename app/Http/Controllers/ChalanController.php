<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chalan;
use App\Models\Ticket;

class ChalanController extends Controller
{
    // CREATE a chalan
    public function create(Request $request)
    {
        $request->validate([
            'chalan_number' => 'required|string|max:50', // USER MUST ENTER IT
            'ticket_ids'   => 'required|array|max:50',
            'ticket_ids.*' => 'exists:tickets,id',
        ]);

        // Create the chalan (NO UNIQUE / NO RANDOM ANYMORE)
        $chalan = Chalan::create([
            'chalan_number' => $request->chalan_number,
            'ticket_ids'    => $request->ticket_ids,
        ]);

        // Link tickets to this chalan
        Ticket::whereIn('id', $request->ticket_ids)
              ->update(['chalan_id' => $chalan->id]);

        return response()->json([
            'message' => 'Chalan created successfully',
            'chalan'  => $chalan
        ], 201);
    }

    // UPDATE a chalan (user can also update chalan_number if needed)
    public function update(Request $request, $id)
    {
        $chalan = Chalan::find($id);
        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }

        $request->validate([
            'chalan_number'      => 'string|max:50', // user can update it
            'add_ticket_ids'     => 'array',
            'add_ticket_ids.*'   => 'exists:tickets,id',
            'remove_ticket_ids'  => 'array',
            'remove_ticket_ids.*'=> 'exists:tickets,id',
        ]);

        if ($request->has('chalan_number')) {
            $chalan->chalan_number = $request->chalan_number;
        }

        $currentTickets = $chalan->ticket_ids ?? [];

        if ($request->has('add_ticket_ids')) {
            $currentTickets = array_unique(array_merge($currentTickets, $request->add_ticket_ids));

            Ticket::whereIn('id', $request->add_ticket_ids)
                  ->update(['chalan_id' => $chalan->id]);
        }

        if ($request->has('remove_ticket_ids')) {
            $currentTickets = array_diff($currentTickets, $request->remove_ticket_ids);

            Ticket::whereIn('id', $request->remove_ticket_ids)
                  ->update(['chalan_id' => null]);
        }

        $chalan->ticket_ids = array_values($currentTickets);
        $chalan->save();

        return response()->json([
            'message' => 'Chalan updated successfully',
            'chalan'  => $chalan
        ], 200);
    }

    public function destroy($id)
    {
        $chalan = Chalan::find($id);
        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }

        Ticket::whereIn('id', $chalan->ticket_ids ?? [])
              ->update(['chalan_id' => null]);

        $chalan->delete();

        return response()->json(['message' => 'Chalan deleted successfully'], 200);
    }

    public function index()
    {
        return response()->json(Chalan::with('tickets')->get(), 200);
    }

    public function show($id)
    {
        $chalan = Chalan::with('tickets')->find($id);

        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }

        return response()->json($chalan, 200);
    }
}
