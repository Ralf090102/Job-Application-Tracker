<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobPostingExtractionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Phase 1 (Roadmap.md): smallest possible slice proving Laravel -> React works
// across origins. Nothing behind auth yet, no real data — just a fixed
// response the frontend can prove it actually fetched over HTTP.
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// Also doubles as the frontend's "am I logged in?" check on page load.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Phase 6: session/cookie login — deliberately public (you can't log in
// while already required to be logged in). The frontend must hit
// GET /sanctum/csrf-cookie (Sanctum's own route) before either of these.
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Phase 5: raw posting text in, structured (unsaved) fields out. Declared
// before the apiResource below on principle — a static path shouldn't rely
// on not colliding with the resource's wildcard route, even though POST
// here doesn't actually overlap any apiResource-registered method.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/job-applications/extract', [JobPostingExtractionController::class, 'store']);

    // Phase 3: index/store/show/update/destroy, all under /api/job-applications.
    Route::apiResource('job-applications', JobApplicationController::class);
});
