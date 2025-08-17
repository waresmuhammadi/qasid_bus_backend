<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class authController extends Controller
{
    public function login(Request $request)
    {
        // Validate input locally
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Call the MAIN dashboard API
        $response = Http::post('http://127.0.0.1:8000/api/companies/login', [
            'username' => $request->username,
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Get token + company details
        $data = $response->json();

        // Store token in session (or JWT for SPA/frontend)
        session(['company_token' => $data['token'], 'company' => $data['company']]);

        return response()->json([
            'message' => 'Login successful',
            'company' => $data['company'],
            'token' => $data['token']
        ]);
    }

    public function logout(Request $request)
    {
        session()->forget(['company_token', 'company']);
        return response()->json(['message' => 'Logged out successfully']);
    }
}
