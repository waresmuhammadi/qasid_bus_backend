<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cleaner;

class cleanerController extends Controller
{
    // ✅ Get all cleaners
    public function getCleaners()
    {
        $cleaners = Cleaner::all();
        return response()->json($cleaners);
    }

    // ✅ Create new cleaner
    public function createCleaner(Request $request)
    {
        $request->validate([
            'cleaner_name' => 'required|string|max:255',
            'cleaner_phone' => 'required|string|max:20',
        ]);

        $cleaner = Cleaner::create([
            'cleaner_name' => $request->cleaner_name,
            'cleaner_phone' => $request->cleaner_phone,
        ]);

        return response()->json([
            'message' => 'Cleaner created successfully!',
            'cleaner' => $cleaner,
        ], 201);
    }

    // ✅ Get one cleaner
    public function getCleanerById($id)
    {
        $cleaner = Cleaner::find($id);
        if (!$cleaner) {
            return response()->json(['message' => 'Cleaner not found'], 404);
        }
        return response()->json($cleaner);
    }

    // ✅ Update cleaner
    public function updateCleaner(Request $request, $id)
    {
        $cleaner = Cleaner::find($id);
        if (!$cleaner) {
            return response()->json(['message' => 'Cleaner not found'], 404);
        }

        $cleaner->update($request->only(['cleaner_name', 'cleaner_phone']));

        return response()->json([
            'message' => 'Cleaner updated successfully!',
            'cleaner' => $cleaner,
        ]);
    }

    // ✅ Delete cleaner
    public function deleteCleaner($id)
    {
        $cleaner = Cleaner::find($id);
        if (!$cleaner) {
            return response()->json(['message' => 'Cleaner not found'], 404);
        }

        $cleaner->delete();
        return response()->json(['message' => 'Cleaner deleted successfully!']);
    }
}
