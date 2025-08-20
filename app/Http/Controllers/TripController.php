<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

class TripController extends Controller
{
    private $afghanMonths = [
        1 => 'Hamal',
        2 => 'Saur',
        3 => 'Jawza',
        4 => 'Saratan',
        5 => 'Asad',
        6 => 'Sunbula',
        7 => 'Mizan',
        8 => 'Aqrab',
        9 => 'Qaws',
        10 => 'Jadi',
        11 => 'Dalwa',
        12 => 'Hoot',
    ];

    private function formatTrip($trip)
    {
        $jalali = Jalalian::forge($trip->departure_date);
        $month = $this->afghanMonths[$jalali->getMonth()];

        $trip->departure_date_dari = $jalali->getDay() . ' ' . $month . ' ' . $jalali->getYear();
        $trip->departure_time_ampm = date("h:i A", strtotime($trip->departure_time));

        return $trip;
    }

    // List all trips for logged-in company
    public function index(Request $request)
    {
        $company = $request->get('company');
        $trips = Trip::where('company_id', $company['id'])->get();

        $trips->transform(function ($trip) {
            return $this->formatTrip($trip);
        });

        return response()->json($trips);
    }

    // Store a new trip
    public function store(Request $request)
    {
        $request->validate([
            'from' => 'required|string|max:255',
            'to' => 'required|string|max:255',
            'departure_time' => 'required|date_format:H:i',
            'departure_date' => 'required|string',
            'departure_terminal' => 'required|string|max:255',
            'arrival_terminal' => 'required|string|max:255',
        ]);

        $company = $request->get('company');

        $gregorianDate = CalendarUtils::createDatetimeFromFormat('Y-m-d', $request->departure_date)
            ->format('Y-m-d');

        $trip = Trip::create([
            'company_id' => $company['id'],
            'from' => $request->from,
            'to' => $request->to,
            'departure_time' => $request->departure_time,
            'departure_date' => $gregorianDate,
            'departure_terminal' => $request->departure_terminal,
            'arrival_terminal' => $request->arrival_terminal,
        ]);

        $trip = $this->formatTrip($trip);

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

        $trip = $this->formatTrip($trip);

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
            'departure_date' => 'sometimes|required|string',
            'departure_terminal' => 'sometimes|required|string|max:255',
            'arrival_terminal' => 'sometimes|required|string|max:255',
        ]);

        $data = $request->all();

        if ($request->has('departure_date')) {
            $data['departure_date'] = CalendarUtils::createDatetimeFromFormat('Y-m-d', $request->departure_date)
                ->format('Y-m-d');
        }

        $trip->update($data);

        $trip = $this->formatTrip($trip);

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

    // Public trips list (no login)
    public function publicIndex(Request $request)
    {
        $companyId = $request->query('company_id');
        $trips = $companyId 
            ? Trip::where('company_id', $companyId)->get() 
            : Trip::all();

        $trips->transform(function ($trip) {
            return $this->formatTrip($trip);
        });

        return response()->json($trips);
    }
}
