<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckOwnerToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = session('company_token');

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Call Owner Dashboard API to validate token
        $response = Http::withToken($token)
            ->get('http://127.0.0.1:8000/api/companies/check-token');

        if ($response->failed()) {
            session()->forget('company_token');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Store company info from Owner Dashboard in request for controllers
        $request->merge(['company' => $response->json()['company']]);

        return $next($request);
    }
}
