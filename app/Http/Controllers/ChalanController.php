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
            'ticket_ids'   => 'required|array|max:50',
            'ticket_ids.*' => 'exists:tickets,id',
        ]);

        // Generate a unique 5-digit chalan number
        do {
            $chalanNumber = random_int(10000, 99999);
        } while (Chalan::where('chalan_number', $chalanNumber)->exists());

        // Create the chalan
        $chalan = Chalan::create([
            'chalan_number' => $chalanNumber,
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

    // UPDATE a chalan (add/remove tickets)
    public function update(Request $request, $id)
    {
        $chalan = Chalan::find($id);
        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }

        $request->validate([
            'add_ticket_ids'    => 'array',
            'add_ticket_ids.*'  => 'exists:tickets,id',
            'remove_ticket_ids' => 'array',
            'remove_ticket_ids.*' => 'exists:tickets,id',
        ]);

        $currentTickets = $chalan->ticket_ids ?? [];

        // Add new tickets
        if ($request->has('add_ticket_ids')) {
            $currentTickets = array_unique(array_merge($currentTickets, $request->add_ticket_ids));
            Ticket::whereIn('id', $request->add_ticket_ids)
                  ->update(['chalan_id' => $chalan->id]);
        }

        // Remove tickets
        if ($request->has('remove_ticket_ids')) {
            $currentTickets = array_diff($currentTickets, $request->remove_ticket_ids);
            Ticket::whereIn('id', $request->remove_ticket_ids)
                  ->update(['chalan_id' => null]);
        }

        // Save updated ticket list
        $chalan->ticket_ids = array_values($currentTickets);
        $chalan->save();

        return response()->json([
            'message' => 'Chalan updated successfully',
            'chalan'  => $chalan
        ], 200);
    }

    // DELETE a chalan
    public function destroy($id)
    {
        $chalan = Chalan::find($id);
        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }

        // Unlink tickets before deleting
        Ticket::whereIn('id', $chalan->ticket_ids ?? [])
              ->update(['chalan_id' => null]);

        $chalan->delete();

        return response()->json(['message' => 'Chalan deleted successfully'], 200);
    }

    // LIST all chalans
    public function index()
    {
        $chalans = Chalan::with('tickets')->get();
        return response()->json($chalans, 200);
    }

    // SHOW single chalan with ticket details
    public function show($id)
    {
        $chalan = Chalan::with('tickets')->find($id);

        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }

        return response()->json($chalan, 200);
    }
}
