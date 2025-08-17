<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VerifyCompanyToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken(); // Get token from Authorization: Bearer <token>
        
        if (!$token) {
            return response()->json(['message' => 'Unauthorized - No token'], 401);
        }

        // Call Project 1 API to validate token
        $response = Http::withToken($token)
            ->get('http://127.0.0.1:8000/api/companies/me');

        if ($response->failed()) {
            return response()->json(['message' => 'Unauthorized - Invalid token'], 401);
        }

        // Attach company info to the request
        $request->merge([
            'company' => $response->json()['company']
        ]);

        return $next($request);
    }
}
