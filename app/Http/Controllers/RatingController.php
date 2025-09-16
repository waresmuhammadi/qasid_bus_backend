<?php
// app/Http/Controllers/RatingController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rating;
use App\Models\Trip;

class RatingController extends Controller
{
    // Add a new rating
    public function store(Request $request, $tripId)
    {
        $request->validate([
            'rate' => 'required|integer|min:1|max:5',
        ]);

        $trip = Trip::findOrFail($tripId);

        $rating = Rating::create([
            'trip_id' => $trip->id,
            'rate' => $request->rate,
        ]);

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating' => $rating
        ], 201);
    }

    // Ratings summary
    public function summary($tripId)
    {
        $trip = Trip::findOrFail($tripId);

        return response()->json([
            'trip_id' => $tripId,
            'average_rating' => round($trip->averageRating(), 2),
            'total_ratings' => $trip->ratingsCount(),
        ]);
    }



    public function count($tripId)
    {
        $trip = Trip::findOrFail($tripId);

        return response()->json([
            'trip_id' => $tripId,
            'total_ratings' => $trip->ratingsCount(),
        ]);
    }


    public function breakdown($tripId)
{
    $trip = Trip::findOrFail($tripId);

    // Group ratings by "rate" value
    $counts = $trip->ratings()
        ->selectRaw('rate, COUNT(*) as count')
        ->groupBy('rate')
        ->pluck('count', 'rate');

    // Build full breakdown (make sure missing stars show 0)
    $breakdown = [];
    for ($i = 1; $i <= 5; $i++) {
        $breakdown[$i] = $counts[$i] ?? 0;
    }

    return response()->json([
        'trip_id' => $tripId,
        'ratings' => $breakdown,
        'total_ratings' => $trip->ratingsCount(),
        'average_rating' => round($trip->averageRating(), 2),
    ]);
}


public function totalScore($tripId)
{
    $trip = Trip::findOrFail($tripId);

    // Sum of all rates for this trip
    $totalRatingScore = $trip->ratings()->sum('rate');

    return response()->json([
        'trip_id' => $tripId,
        'total_rating_score' => $totalRatingScore,
        'average_rating' => round($trip->averageRating(), 2),
        'total_ratings' => $trip->ratingsCount(),
    ]);
}




}
