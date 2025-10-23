<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponController extends Controller
{
    // List all coupons
    public function index()
    {
        $coupons = Coupon::all();
        return response()->json([
            'message' => 'Coupons retrieved successfully',
            'coupons' => $coupons
        ], 200);
    }

    // Show a single coupon
    public function show($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        return response()->json([
            'message' => 'Coupon retrieved successfully',
            'coupon' => $coupon
        ], 200);
    }

    // Create a new coupon
    public function store(Request $request)
    {
        $request->validate([
            'code'        => 'required|string|unique:coupons,code',
            'amount'      => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        $coupon = Coupon::create([
            'code'        => $request->code,
            'amount'      => $request->amount,
            'expiry_date' => $request->expiry_date,
            'usage_limit' => $request->usage_limit,
        ]);

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon'  => $coupon
        ], 201);
    }

    // Update an existing coupon
    public function update(Request $request, $id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        $request->validate([
            'code'        => 'sometimes|required|string|unique:coupons,code,' . $id,
            'amount'      => 'sometimes|required|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        $coupon->update($request->only(['code', 'amount', 'expiry_date', 'usage_limit']));

        return response()->json([
            'message' => 'Coupon updated successfully',
            'coupon'  => $coupon
        ], 200);
    }

    // Delete a coupon
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully'
        ], 200);
    }
}
