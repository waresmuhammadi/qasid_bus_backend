<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    // Get all drivers
    public function getAllDrivers()
    {
        $drivers = Driver::all();
        return response()->json($drivers);
    }

    // Get single driver
    public function getDriver($id)
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        return response()->json($driver);
    }

    // Create a new driver
    public function createDriver(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'father_name' => 'required|string', // ✅ corrected
            'phone' => 'nullable|string',
            'license_number' => 'required|string|unique:drivers,license_number',
        ]);

        $driver = Driver::create($request->all());
        return response()->json($driver, 201);
    }

    // Update a driver
    public function updateDriver(Request $request, $id)
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'father_name' => 'required|string', // ✅ corrected
            'phone' => 'nullable|string',
            'license_number' => 'required|string|unique:drivers,license_number,' . $driver->id,
        ]);

        $driver->update($request->all());
        return response()->json($driver);
    }

    // Delete a driver
    public function deleteDriver($id)
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        $driver->delete();
        return response()->json(['message' => 'Driver deleted successfully']);
    }
}
