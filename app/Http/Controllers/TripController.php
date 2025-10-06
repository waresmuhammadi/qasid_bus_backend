<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Trip;
use Morilog\Jalali\Jalalian;
use App\Models\Rating;

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

    if (count($parts) === 3) {
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];
        $monthName = $this->afghanMonths[$month] ?? '';
        $trip->departure_date_dari = "$day $monthName $year";
    } else {
        $trip->departure_date_dari = $trip->departure_date; // fallback
    }

    $trip->departure_time_ampm = date("h:i A", strtotime($trip->departure_time));

    // Format bus type and price for display
    if (is_array($trip->bus_type)) {
        $trip->formatted_bus_type = implode(', ', $trip->bus_type);
    } else {
        $trip->formatted_bus_type = $trip->bus_type;
    }

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
        $date = $request->query('date');
        $query->where(function($q) use ($date) {
            $q->where('departure_date', $date)
              ->orWhere('all_days', true); // match trips that are available every day
        });
    }

    if ($request->has('bus_type')) {
        $busType = $request->query('bus_type');
        $query->whereJsonContains('bus_type', $busType);
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
        'departure_terminal' => 'required|string|max:255',
        'arrival_terminal' => 'required|string|max:255',
        'bus_type' => 'required|array',
        'bus_type.*' => 'in:VIP,580',
        'price_vip' => 'required_if:bus_type,VIP|numeric|min:0',
        'price_580' => 'required_if:bus_type,580|numeric|min:0',
        'all_days' => 'boolean',
    ]);

    $company = $request->get('company');

    // Departure date only if not all_days
    $departureDate = null;
    if (!$request->boolean('all_days')) {
        $request->validate([
            'departure_date_jalali.year' => 'required|integer',
            'departure_date_jalali.month' => 'required|integer',
            'departure_date_jalali.day' => 'required|integer',
        ]);
        $jalali = $request->departure_date_jalali;
        $departureDate = sprintf("%04d-%02d-%02d", $jalali['year'], $jalali['month'], $jalali['day']);
    }

    // Create price array based on bus types
    $prices = [];
    if (in_array('VIP', $request->bus_type)) {
        $prices['VIP'] = $request->price_vip;
    }
    if (in_array('580', $request->bus_type)) {
        $prices['580'] = $request->price_580;
    }

    $trip = Trip::create([
        'company_id' => $company['id'],
        'from' => $request->from,
        'to' => $request->to,
        'departure_time' => $request->departure_time,
        'departure_date' => $departureDate, // null if all_days = true
        'departure_terminal' => $request->departure_terminal,
        'arrival_terminal' => $request->arrival_terminal,
        'bus_type' => $request->bus_type,
        'prices' => $prices,
        'all_days' => $request->boolean('all_days', false),
    ]);

    $trip = $this->formatTrip($trip);

    return response()->json([
        'message' => 'Trip created successfully',
        'trip' => $trip
    ], 201);
}


    // Update a trip
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
    'departure_date_jalali.year' => 'sometimes|required|integer',
    'departure_date_jalali.month' => 'sometimes|required|integer',
    'departure_date_jalali.day' => 'sometimes|required|integer',
    'departure_terminal' => 'sometimes|required|string|max:255',
    'arrival_terminal' => 'sometimes|required|string|max:255',
    'bus_type' => 'sometimes|required|array',
    'bus_type.*' => 'in:VIP,580',
    // ✅ FIX: Conditional validation - only require price if bus type is selected
    'price_vip' => function ($attribute, $value, $fail) use ($request) {
        if (in_array('VIP', $request->bus_type ?? []) && (is_null($value) || $value === '')) {
            $fail('The VIP price is required when VIP bus type is selected.');
        }
    },
    'price_580' => function ($attribute, $value, $fail) use ($request) {
        if (in_array('580', $request->bus_type ?? []) && (is_null($value) || $value === '')) {
            $fail('The 580 price is required when 580 bus type is selected.');
        }
    },
    'all_days' => 'boolean',
]);

    $data = $request->all();

    // Handle departure date
    if ($request->boolean('all_days')) {
        $data['departure_date'] = null;
    } else {
        if ($request->has('departure_date_jalali') && $request->departure_date_jalali) {
            $jalali = $request->departure_date_jalali;

            // Decode if string
            if (is_string($jalali)) {
                $jalali = json_decode($jalali, true);
            }

            if (is_array($jalali)) {
                $data['departure_date'] = sprintf(
                    "%04d-%02d-%02d",
                    $jalali['year'],
                    $jalali['month'],
                    $jalali['day']
                );
            }
        }
    }

    // Handle departure time
    if ($request->has('departure_time') && $request->departure_time) {
        $time = $request->departure_time;
        if (is_string($time)) {
            $time = json_decode($time, true);
        }
        if (is_array($time)) {
            $data['departure_time'] = sprintf(
                "%02d:%02d:00",
                $time['hour'],
                $time['minute']
            );
        }
    }

    // Handle arrival time
    if ($request->has('arrival_time') && $request->arrival_time) {
        $time = $request->arrival_time;
        if (is_string($time)) {
            $time = json_decode($time, true);
        }
        if (is_array($time)) {
            $data['arrival_time'] = sprintf(
                "%02d:%02d:00",
                $time['hour'],
                $time['minute']
            );
        }
    }

    // Handle amenities
    if ($request->has('amenities')) {
        $amenities = $request->amenities;
        if (is_string($amenities)) {
            $amenities = json_decode($amenities, true);
        }
        if (is_array($amenities)) {
            $data['amenities'] = json_encode($amenities);
        }
    }

    // ✅ FIX: Handle prices update when bus types change
    if ($request->has('bus_type')) {
        $prices = [];
        
        // Update VIP price if VIP is in bus types
        if (in_array('VIP', $request->bus_type) && $request->has('price_vip')) {
            $prices['VIP'] = $request->price_vip;
        }
        
        // Update 580 price if 580 is in bus types
        if (in_array('580', $request->bus_type) && $request->has('price_580')) {
            $prices['580'] = $request->price_580;
        }
        
        // Set the updated prices
        $data['prices'] = $prices;
    }

    // Update trip
    $trip->update($data);

    // Refresh the trip to get updated data
    $trip->refresh();

    // Format trip for response
    if (method_exists($this, 'formatTrip')) {
        $trip = $this->formatTrip($trip);
    }

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