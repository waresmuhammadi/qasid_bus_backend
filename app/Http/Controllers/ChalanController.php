<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chalan;
use App\Models\Ticket;
use Illuminate\Support\Str;

class ChalanController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'ticket_ids'   => 'required|array|max:50',
            'ticket_ids.*' => 'exists:tickets,id',
        ]);

        // Generate unique chalan number
        do {
            $chalanNumber = 'CHLN-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Chalan::where('chalan_number', $chalanNumber)->exists());

        // Create chalan
        $chalan = Chalan::create([
            'chalan_number' => $chalanNumber,
            'ticket_ids'    => $request->ticket_ids,
        ]);

        // Optional: update tickets to link to this chalan
        Ticket::whereIn('id', $request->ticket_ids)
              ->update(['chalan_id' => $chalan->id]);

        return response()->json([
            'message' => 'Chalan created successfully',
            'chalan'  => $chalan
        ], 201);
    }

    // Optional: list all chalans
    public function index()
    {
        $chalans = Chalan::all();
        return response()->json($chalans, 200);
    }

    // Optional: show single chalan
    public function show($id)
    {
        $chalan = Chalan::find($id);
        if (!$chalan) {
            return response()->json(['message' => 'Chalan not found'], 404);
        }
        return response()->json($chalan, 200);
    }
}
