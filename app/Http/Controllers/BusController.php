<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bus;

class BusController extends Controller
{
    // List all buses
    public function index()
    {
        $buses = Bus::all();
        return response()->json($buses);
    }

    // Create a new bus
    public function store(Request $request)
    {
        $request->validate([
            'bus_no'        => 'required|string|unique:buses',
            'number_plate'  => 'required|string|unique:buses',
            'license_number'=> 'required|string|unique:buses',
            'type'          => 'required|in:vip,580', // ✅ updated
        ]);

        $company = $request->get('company'); 

        $bus = Bus::create([
            'bus_no'        => $request->bus_no,
            'number_plate'  => $request->number_plate,
            'license_number'=> $request->license_number,
            'type'          => $request->type,
            'company_id'    => $company['id'], // link to logged-in company
        ]);

        return response()->json([
            'message' => 'Bus created successfully',
            'bus'     => $bus,
        ], 201);
    }

    // Get a single bus
    public function show($id)
    {
        $bus = Bus::find($id);
        if (!$bus) {
            return response()->json(['message' => 'Bus not found'], 404);
        }
        return response()->json($bus);
    }

    // Update a bus
    public function update(Request $request, $id)
    {
        $bus = Bus::find($id);
        if (!$bus) {
            return response()->json(['message' => 'Bus not found'], 404);
        }

        $request->validate([
            'bus_no'        => 'sometimes|required|string|unique:buses,bus_no,' . $bus->id,
            'number_plate'  => 'sometimes|required|string|unique:buses,number_plate,' . $bus->id,
            'license_number'=> 'sometimes|required|string|unique:buses,license_number,' . $bus->id,
            'type'          => 'sometimes|required|in:vip,580', // ✅ updated
        ]);

        $bus->update($request->only(['bus_no', 'number_plate', 'license_number', 'type']));

        return response()->json([
            'message' => 'Bus updated successfully',
            'bus'     => $bus,
        ]);
    }

    // Delete a bus
    public function destroy($id)
    {
        $bus = Bus::find($id);
        if (!$bus) {
            return response()->json(['message' => 'Bus not found'], 404);
        }

        $bus->delete();

        return response()->json(['message' => 'Bus deleted successfully']);
    }
}
