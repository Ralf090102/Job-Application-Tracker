<?php

use App\Http\Controllers\JobApplicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Phase 1 (Roadmap.md): smallest possible slice proving Laravel -> React works
// across origins. Nothing behind auth yet, no real data — just a fixed
// response the frontend can prove it actually fetched over HTTP.
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Phase 3: index/store/show/update/destroy, all under /api/job-applications.
// Not behind auth yet — that's Phase 6, deliberately last per the roadmap.
Route::apiResource('job-applications', JobApplicationController::class);
