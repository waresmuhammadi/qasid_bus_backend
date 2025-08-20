<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;

class TripController extends Controller
{
    // List all trips for logged-in company
    public function index(Request $request)
    {
        $company = $request->get('company');
        $trips = Trip::where('company_id', $company['id'])->get();
        return response()->json($trips);
    }

    // Store a new trip
    public function store(Request $request)
    {
        $request->validate([
            'from' => 'required|string|max:255',
            'to' => 'required|string|max:255',
            'departure_time' => 'required|date_format:H:i',
            'departure_date' => 'required|date|after_or_equal:today',
            'departure_terminal' => 'required|string|max:255',
            'arrival_terminal' => 'required|string|max:255',
        ]);

        $company = $request->get('company');

        $trip = Trip::create([
            'company_id' => $company['id'],
            'from' => $request->from,
            'to' => $request->to,
            'departure_time' => $request->departure_time,
            'departure_date' => $request->departure_date,
            'departure_terminal' => $request->departure_terminal,
            'arrival_terminal' => $request->arrival_terminal,
        ]);

        return response()->json([
            'message' => 'Trip created successfully',
            'trip' => $trip
        ], 201);
    }

    // Show a single trip
    public function show(Request $request, $id)
    {
        $company = $request->get('company');
        $trip = Trip::where('company_id', $company['id'])->find($id);

        if (!$trip) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        return response()->json($trip);
    }

    // Update a trip
    public function update(Request $request, $id)
    {
        $company = $request->get('company');
        $trip = Trip::where('company_id', $company['id'])->find($id);

        if (!$trip) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        $request->validate([
            'from' => 'sometimes|required|string|max:255',
            'to' => 'sometimes|required|string|max:255',
            'departure_time' => 'sometimes|required|date_format:H:i',
            'departure_date' => 'sometimes|required|date|after_or_equal:today',
            'departure_terminal' => 'sometimes|required|string|max:255',
            'arrival_terminal' => 'sometimes|required|string|max:255',
        ]);

        $trip->update($request->all());

        return response()->json([
            'message' => 'Trip updated successfully',
            'trip' => $trip
        ]);
    }

    // Delete a trip
    public function destroy(Request $request, $id)
    {
        $company = $request->get('company');
        $trip = Trip::where('company_id', $company['id'])->find($id);

        if (!$trip) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        $trip->delete();

        return response()->json(['message' => 'Trip deleted successfully']);
    }


 public function publicIndex(Request $request)
    {
        $companyId = $request->query('company_id'); // e.g. ?company_id=1
        $trips = $companyId 
            ? Trip::where('company_id', $companyId)->get() 
            : Trip::all();

        return response()->json($trips);
    }


}
