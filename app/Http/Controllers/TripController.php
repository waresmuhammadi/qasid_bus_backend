<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use Morilog\Jalali\Jalalian;

class TripController extends Controller
{
    private $afghanMonths = [
        1 => 'حمل', 2 => 'ثور', 3 => 'جوزا', 4 => 'سرطان',
        5 => 'اسد', 6 => 'سنبله', 7 => 'میزان', 8 => 'عقرب',
        9 => 'قوس', 10 => 'جدی', 11 => 'دلو', 12 => 'حوت',
    ];

    private function formatTrip($trip)
    {
        $parts = explode('-', $trip->departure_date);
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];
        $monthName = $this->afghanMonths[$month] ?? '';
        $trip->departure_date_dari = "$day $monthName $year";
        $trip->departure_time_ampm = date("h:i A", strtotime($trip->departure_time));
        return $trip;
    }

    // Public trips with filtering
    public function publicIndex(Request $request)
    {
        $query = Trip::query();
        
        if ($request->has('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }
        
        if ($request->has('from')) {
            $query->where('from', 'like', '%' . $request->query('from') . '%');
        }
        
        if ($request->has('to')) {
            $query->where('to', 'like', '%' . $request->query('to') . '%');
        }
        
        if ($request->has('date')) {
            $query->where('departure_date', $request->query('date'));
        }
        
        $trips = $query->get();
        $trips->transform(fn($trip) => $this->formatTrip($trip));

        return response()->json($trips);
    }

    // Store a new trip
    public function store(Request $request)
    {
        $request->validate([
            'from' => 'required|string|max:255',
            'to' => 'required|string|max:255',
            'departure_time' => 'required|date_format:H:i',
            'departure_date_jalali.year' => 'required|integer',
            'departure_date_jalali.month' => 'required|integer',
            'departure_date_jalali.day' => 'required|integer',
            'departure_terminal' => 'required|string|max:255',
            'arrival_terminal' => 'required|string|max:255',
            'price' => 'required|numeric|min:0', // ✅ Price required
        ]);

        $company = $request->get('company');
        $jalali = $request->departure_date_jalali;
        $departureDate = sprintf("%04d-%02d-%02d", $jalali['year'], $jalali['month'], $jalali['day']);

        $trip = Trip::create([
            'company_id' => $company['id'],
            'from' => $request->from,
            'to' => $request->to,
            'departure_time' => $request->departure_time,
            'departure_date' => $departureDate,
            'departure_terminal' => $request->departure_terminal,
            'arrival_terminal' => $request->arrival_terminal,
            'price' => $request->price, // ✅ Save price
        ]);

        $trip = $this->formatTrip($trip);

        return response()->json([
            'message' => 'Trip created successfully',
            'trip' => $trip
        ], 201);
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
            'departure_date_jalali.year' => 'sometimes|required|integer',
            'departure_date_jalali.month' => 'sometimes|required|integer',
            'departure_date_jalali.day' => 'sometimes|required|integer',
            'departure_terminal' => 'sometimes|required|string|max:255',
            'arrival_terminal' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0', // ✅ Price allowed in update
        ]);

        $data = $request->all();

        if ($request->has('departure_date_jalali')) {
            $jalali = $request->departure_date_jalali;
            $data['departure_date'] = sprintf("%04d-%02d-%02d", $jalali['year'], $jalali['month'], $jalali['day']);
        }

        $trip->update($data);

        $trip = $this->formatTrip($trip);

        return response()->json([
            'message' => 'Trip updated successfully',
            'trip' => $trip
        ]);
    }

    // List trips for logged-in company
    public function index(Request $request)
    {
        $company = $request->get('company');
        $trips = Trip::where('company_id', $company['id'])->get();

        $trips->transform(fn($trip) => $this->formatTrip($trip));

        return response()->json($trips);
    }

    // Show single trip
    public function show(Request $request, $id)
    {
        $company = $request->get('company');
        $trip = Trip::where('company_id', $company['id'])->find($id);

        if (!$trip) return response()->json(['message' => 'Trip not found'], 404);

        $trip = $this->formatTrip($trip);
        return response()->json($trip);
    }

    // Delete trip
    public function destroy(Request $request, $id)
    {
        $company = $request->get('company');
        $trip = Trip::where('company_id', $company['id'])->find($id);

        if (!$trip) return response()->json(['message' => 'Trip not found'], 404);

        $trip->delete();
        return response()->json(['message' => 'Trip deleted successfully']);
    }
}
