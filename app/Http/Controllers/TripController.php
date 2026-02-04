<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Ticket;
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
    // ✅ Handle multiple date ranges (array)
    if (is_array($trip->departure_dates_range) && count($trip->departure_dates_range) > 0) {
        $trip->departure_date_dari = collect($trip->departure_dates_range)->map(function ($datePair) {
            if (is_array($datePair) && isset($datePair['jalali'])) {
                $parts = explode('-', $datePair['jalali']);
                if (count($parts) === 3) {
                    $year = (int)$parts[0];
                    $month = (int)$parts[1];
                    $day = (int)$parts[2];
                    $monthName = $this->afghanMonths[$month] ?? '';
                    return "$day $monthName $year";
                }
            }
            return $datePair; // fallback
        });
    }
    // In your formatTrip method, add this:
if ($trip->is_range && $trip->departure_dates_range) {
    $trip->departure_dates_jalali = collect($trip->departure_dates_range)->map(function ($datePair) {
        if (is_array($datePair) && isset($datePair['jalali'])) {
            $parts = explode('-', $datePair['jalali']);
            if (count($parts) === 3) {
                return [
                    'year' => (int)$parts[0],
                    'month' => (int)$parts[1], 
                    'day' => (int)$parts[2]
                ];
            }
        }
        return null;
    })->filter()->toArray();
}

    // ✅ Handle single-day trip (for backward compatibility)
    elseif (!empty($trip->departure_date)) {
        $parts = explode('-', $trip->departure_date);
        if (count($parts) === 3) {
            $year = (int)$parts[0];
            $month = (int)$parts[1];
            $day = (int)$parts[2];
            $monthName = $this->afghanMonths[$month] ?? '';
            $trip->departure_date_dari = "$day $monthName $year";
        } else {
            $trip->departure_date_dari = $trip->departure_date;
        }
    } 
    // ✅ If all_days, just mark it textually
    elseif ($trip->all_days) {
        $trip->departure_date_dari = ['هره ورځ'];
    } 
    // ✅ Otherwise fallback
    else {
        $trip->departure_date_dari = null;
    }

    // ✅ Format departure time
    $trip->departure_time_ampm = date("h:i A", strtotime($trip->departure_time));

    // ✅ Format bus type
    if (is_array($trip->bus_type)) {
        $trip->formatted_bus_type = implode(', ', $trip->bus_type);
    } else {
        $trip->formatted_bus_type = $trip->bus_type;
    }

    // ✅ FIX: Format additional capacity without modifying the model directly
    // Instead of setting properties, return an array or use a different approach
    $formattedAdditionalCapacity = [];
    if (is_array($trip->additional_capacity)) {
        foreach ($trip->additional_capacity as $busType => $capacity) {
            $formattedAdditionalCapacity[] = "$busType: +$capacity";
        }
    }
    
    // Add formatted properties as array elements (not model properties)
    return array_merge($trip->toArray(), [
        'formatted_additional_capacity' => !empty($formattedAdditionalCapacity) 
            ? implode(', ', $formattedAdditionalCapacity) 
            : 'None'
    ]);
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
    $isJalali = str_starts_with($date, '14');

    $query->where(function ($q) use ($date, $isJalali) {
        // For range trips (departure_dates_range)
        if ($isJalali) {
            $q->whereJsonContains('departure_dates_range', [['jalali' => $date]]);
        } else {
            $q->whereJsonContains('departure_dates_range', [['gregorian' => $date]]);
        }

        // For single-day trips (departure_date)
        if ($isJalali) {
            $q->orWhere('departure_date', $date);
        } else {
            // Convert Gregorian to Jalali for single-day trips if needed
            // Or store both formats in departure_date
            $q->orWhere('departure_date', $date);
        }

        // For all_days trips
        $q->orWhere('all_days', true);
    });
}


    if ($request->has('bus_type')) {
        $busType = $request->query('bus_type');
        $query->whereJsonContains('bus_type', $busType);
    }

    $trips = $query->get();
    // --- CRITICAL FIX: Handle both Gregorian and Jalali dates ---
    $now = now()->setTimezone('Asia/Kabul');
    $currentTime = $now->format('H:i:s');
    $requestedDate = $request->query('date');

    $trips->transform(function ($trip) use ($currentTime, $now, $requestedDate) {
        $isToday = false;
        
        if ($trip->all_days && $requestedDate) {
            // For all_days trips with requested date
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) && substr($requestedDate, 0, 2) === '14') {
                // Jalali date requested
                try {
                    $jalaliParts = explode('-', $requestedDate);
                    $jalalian = new Jalalian((int)$jalaliParts[0], (int)$jalaliParts[1], (int)$jalaliParts[2]);
                    $gregorianRequested = $jalalian->toCarbon()->format('Y-m-d');
                    $todayGregorian = $now->format('Y-m-d');
                    $isToday = ($gregorianRequested === $todayGregorian);
                } catch (\Exception $e) {
                    $isToday = false;
                }
            } else {
                // Gregorian date requested
                $todayGregorian = $now->format('Y-m-d');
                $isToday = ($requestedDate === $todayGregorian);
            }
            
            $trip->can_book = $isToday ? ($trip->departure_time > $currentTime) : true;
        } else if (!$trip->all_days && $trip->departure_date) {
            // For specific date trips
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trip->departure_date) && substr($trip->departure_date, 0, 2) === '14') {
                // Jalali departure date
                try {
                    $jalaliParts = explode('-', $trip->departure_date);
                    $jalalian = new Jalalian((int)$jalaliParts[0], (int)$jalaliParts[1], (int)$jalaliParts[2]);
                    $gregorianDeparture = $jalalian->toCarbon()->format('Y-m-d');
                    $todayGregorian = $now->format('Y-m-d');
                    $isToday = ($gregorianDeparture === $todayGregorian);
                } catch (\Exception $e) {
                    $isToday = false;
                }
            } else {
                // Gregorian departure date
                $todayGregorian = $now->format('Y-m-d');
                $isToday = ($trip->departure_date === $todayGregorian);
            }
            
            $trip->can_book = $isToday ? ($trip->departure_time > $currentTime) : true;
        } else {
            $trip->can_book = true;
        }

        // Format trip for display
        $trip = $this->formatTrip($trip);
        return $trip;
    });

    return response()->json($trips);
}





public function Mobiletrips(Request $request)
{
    $query = Trip::query();

    /* ---------------- FILTERS ---------------- */

    if ($request->filled('company_id')) {
        $query->where('company_id', $request->company_id);
    }

    if ($request->filled('from')) {
        $query->where('from', 'LIKE', '%' . trim($request->from) . '%');
    }

    if ($request->filled('to')) {
        $query->where('to', 'LIKE', '%' . trim($request->to) . '%');
    }

    /* ---------------- DATE FILTER (🔥 REAL FIX) ---------------- */

    if ($request->filled('date')) {
        $date = trim($request->date);

        $query->where(function ($q) use ($date) {

            // ✅ RANGE DATES (TEXT OR JSON SAFE)
            $q->where('departure_dates_range', 'LIKE', '%' . $date . '%');

            // ✅ SINGLE DATE
            $q->orWhere('departure_date', $date);

            // ✅ ALL DAYS
            $q->orWhere('all_days', 1);
        });
    }

    /* ---------------- BUS TYPE ---------------- */

    if ($request->filled('bus_type')) {
        $query->whereJsonContains('bus_type', $request->bus_type);
    }

    $trips = $query->get();

    /* ---------------- TIME FILTER ---------------- */

    $now = now()->setTimezone('Asia/Kabul');
    $currentTime = $now->format('H:i:s');
    $requestedDate = $request->date;

    $trips = $trips->filter(function ($trip) use ($now, $currentTime, $requestedDate) {

        if (!$requestedDate) {
            return true;
        }

        $isToday = false;

        // Jalali requested date
        if (preg_match('/^(13|14)\d{2}-\d{1,2}-\d{1,2}$/', $requestedDate)) {
            try {
                [$y, $m, $d] = explode('-', $requestedDate);
                $gregorian = (new Jalalian($y, $m, $d))->toCarbon()->format('Y-m-d');
                $isToday = ($gregorian === $now->format('Y-m-d'));
            } catch (\Exception $e) {
                return true;
            }
        } else {
            $isToday = ($requestedDate === $now->format('Y-m-d'));
        }

        if ($isToday) {
            return $trip->departure_time > $currentTime;
        }

        return true;
    })->values();

    /* ---------------- FORMAT ---------------- */

    $trips = $trips->map(fn ($trip) => $this->formatTrip($trip));

   return response()->json([
    'trips' => $trips
]);

}






public function tripLocations()
{
    $froms = Trip::whereNotNull('from')
        ->distinct()
        ->pluck('from')
        ->values();

    $tos = Trip::whereNotNull('to')
        ->distinct()
        ->pluck('to')
        ->values();

    return response()->json([
        'from' => $froms,
        'to'   => $tos,
    ]);
}














    // Store a new trip
public function store(Request $request)
{
    $request->validate([
        'from' => 'required|string|max:255',
        'to' => 'required|string|max:255',
        'departure_time' => 'required|string',
        'departure_terminal' => 'required|string|max:255',
        'arrival_terminal' => 'required|string|max:255',
        'additional_capacity_vip' => 'nullable|integer|min:0|max:20',
        'additional_capacity_580' => 'nullable|integer|min:0|max:20',
        'bus_type' => 'required|array',
        'bus_type.*' => 'in:VIP,580',
        'price_vip' => 'required_if:bus_type,VIP|numeric|min:0',
        'price_580' => 'required_if:bus_type,580|numeric|min:0',
        'all_days' => 'boolean',
        'departure_dates_jalali' => 'sometimes|array',
        'departure_date_jalali.year' => 'required_without_all:all_days,departure_dates_jalali|integer',
        'departure_date_jalali.month' => 'required_without_all:all_days,departure_dates_jalali|integer',
        'departure_date_jalali.day' => 'required_without_all:all_days,departure_dates_jalali|integer',
    ]);

    $company = $request->get('company');
    $departureTime = $request->departure_time;

    if (preg_match('/(AM|PM)$/i', $departureTime)) {
        $departureTime = date("H:i", strtotime($departureTime));
    }

    $departureDates = null;
    $departureDate = null;
    $isRange = false;

    // ✅ Automatically detect if multiple dates selected
    if ($request->has('departure_dates_jalali') && is_array($request->departure_dates_jalali) && count($request->departure_dates_jalali) > 1) {
        $isRange = true;
    }

    // ✅ Handle multiple-date (range) trips
    if ($isRange) {
        $departureDates = [];
        $now = now()->setTimezone('Asia/Kabul')->format('Y-m-d');

        foreach ($request->departure_dates_jalali as $jalali) {
            try {
                $jalalian = new \Morilog\Jalali\Jalalian($jalali['year'], $jalali['month'], $jalali['day']);
                $gregorianDate = $jalalian->toCarbon()->format('Y-m-d');
                if ($gregorianDate < $now) {
                    return response()->json(['message' => 'Past dates not allowed'], 422);
                }
                $departureDates[] = [
                    'jalali' => $jalalian->format('Y-m-d'),
                    'gregorian' => $gregorianDate,
                ];
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid Jalali date in range'], 422);
            }
        }
    }
    // ✅ Handle all_days trips
    elseif ($request->boolean('all_days')) {
        $departureDates = null;
    }
    // ✅ Handle single-day trips
    else {
        $jalali = $request->departure_date_jalali;
        $jalalian = new \Morilog\Jalali\Jalalian($jalali['year'], $jalali['month'], $jalali['day']);
        $departureDate = $jalalian->format('Y-m-d');
    }

    // ✅ Handle prices
    $prices = [];
    if (in_array('VIP', $request->bus_type)) $prices['VIP'] = $request->price_vip;
    if (in_array('580', $request->bus_type)) $prices['580'] = $request->price_580;
    // ✅ Handle additional capacity as object
    $additionalCapacity = [];
    if (in_array('VIP', $request->bus_type) && $request->has('additional_capacity_vip')) {
        $additionalCapacity['VIP'] = $request->additional_capacity_vip ?? 0;
    }
    if (in_array('580', $request->bus_type) && $request->has('additional_capacity_580')) {
        $additionalCapacity['580'] = $request->additional_capacity_580 ?? 0;
    }

    // ✅ Create trip
    $trip = Trip::create([
    
        'from' => $request->from,
        'to' => $request->to,
        'departure_time' => $departureTime,
        'departure_terminal' => $request->departure_terminal,
        'arrival_terminal' => $request->arrival_terminal,
      'additional_capacity' => $additionalCapacity,
        'bus_type' => $request->bus_type,
        'prices' => $prices,
        'all_days' => $request->boolean('all_days', false),
        'is_range' => $isRange,
        'departure_date' => $departureDate,
        'departure_dates_range' => $departureDates,
    ]);

    $trip = $this->formatTrip($trip);

    return response()->json([
        'message' => 'Trip created successfully',
        'trip' => $trip,
    ], 201);
}




    // Update a trip
/// In TripController update method, update the validation and handling:
public function update(Request $request, $id)
{
    $trip = Trip::find($id);

    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    $request->validate([
        'from' => 'sometimes|required|string|max:255',
        'to' => 'sometimes|required|string|max:255',
        'departure_date_jalali.year' => 'sometimes|required|integer',
        'departure_dates_jalali' => 'sometimes|array',
        'departure_dates_jalali.*.year' => 'sometimes|required|integer',
        'departure_dates_jalali.*.month' => 'sometimes|required|integer',
        'departure_dates_jalali.*.day' => 'sometimes|required|integer',
        'additional_capacity_vip' => 'sometimes|integer|min:0|max:20',
        'additional_capacity_580' => 'sometimes|integer|min:0|max:20',
        'departure_date_jalali.month' => 'sometimes|required|integer',
        'departure_date_jalali.day' => 'sometimes|required|integer',
        'departure_terminal' => 'sometimes|required|string|max:255',
        'arrival_terminal' => 'sometimes|required|string|max:255',
        'bus_type' => 'sometimes|required|array',
        'bus_type.*' => 'in:VIP,580',
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

    // ✅ FIX: Handle date type changes (all_days, single date, or range)
    $isRange = false;
    $departureDates = null;
    $departureDate = null;

    // Handle multiple-date (range) trips
    if ($request->has('departure_dates_jalali') && is_array($request->departure_dates_jalali) && count($request->departure_dates_jalali) > 0) {
        $isRange = true;
        $departureDates = [];
        $now = now()->setTimezone('Asia/Kabul')->format('Y-m-d');

        foreach ($request->departure_dates_jalali as $jalali) {
            try {
                // Decode if string
                if (is_string($jalali)) {
                    $jalali = json_decode($jalali, true);
                }

                $jalalian = new \Morilog\Jalali\Jalalian($jalali['year'], $jalali['month'], $jalali['day']);
                $gregorianDate = $jalalian->toCarbon()->format('Y-m-d');
                
                if ($gregorianDate < $now) {
                    return response()->json(['message' => 'Past dates not allowed'], 422);
                }
                
                $departureDates[] = [
                    'jalali' => $jalalian->format('Y-m-d'),
                    'gregorian' => $gregorianDate,
                ];
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid Jalali date in range'], 422);
            }
        }
        
        $data['departure_dates_range'] = $departureDates;
        $data['departure_date'] = null;
        $data['is_range'] = true;
        $data['all_days'] = false;
    }
    // Handle all_days trips
    elseif ($request->boolean('all_days')) {
        $data['departure_dates_range'] = null;
        $data['departure_date'] = null;
        $data['is_range'] = false;
        $data['all_days'] = true;
    }
    // Handle single-day trips
    else if ($request->has('departure_date_jalali') && $request->departure_date_jalali) {
        $jalali = $request->departure_date_jalali;

        // Decode if string
        if (is_string($jalali)) {
            $jalali = json_decode($jalali, true);
        }

        if (is_array($jalali)) {
            try {
                $jalalian = new \Morilog\Jalali\Jalalian($jalali['year'], $jalali['month'], $jalali['day']);
                $data['departure_date'] = $jalalian->format('Y-m-d');
                $data['departure_dates_range'] = null;
                $data['is_range'] = false;
                $data['all_days'] = false;
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid Jalali date'], 422);
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

    // ✅ Handle additional capacity as object
    if ($request->has('bus_type')) {
        $additionalCapacity = [];
        
        // Update VIP capacity if VIP is in bus types
        if (in_array('VIP', $request->bus_type) && $request->has('additional_capacity_vip')) {
            $additionalCapacity['VIP'] = $request->additional_capacity_vip ?? 0;
        }
        
        // Update 580 capacity if 580 is in bus types
        if (in_array('580', $request->bus_type) && $request->has('additional_capacity_580')) {
            $additionalCapacity['580'] = $request->additional_capacity_580 ?? 0;
        }
        
        // Set the updated additional capacity
        $data['additional_capacity'] = $additionalCapacity;
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
    public function destroy($id)
{
    $trip = Trip::find($id);

    if (!$trip) {
        return response()->json(['message' => 'Trip not found'], 404);
    }

    $trip->delete();

    return response()->json(['message' => 'Trip deleted successfully']);
}


/**
 * Lock specific seats for a trip with manual subdomain restriction
 */
/**
 * Lock specific seats for a trip with manual subdomain restriction
 */

}