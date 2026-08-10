<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Session/cookie login (Sanctum SPA mode) — not a bearer token. The
     * frontend must have already hit GET /sanctum/csrf-cookie so this
     * POST carries a valid X-XSRF-TOKEN header.
     */
    public function login(LoginRequest $request)
    {
        if (! Auth::attempt($request->validated())) {
            // Same shape as any other 422 — the frontend's existing
            // field-error handling (see api/client.js) just works here too.
            throw ValidationException::withMessages([
                'email' => 'Those credentials don\'t match our records.',
            ]);
        }

        // Prevents session fixation — a fresh session ID after privilege
        // changes, standard practice regardless of guard.
        $request->session()->regenerate();

        return response()->json(['data' => Auth::user()]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
