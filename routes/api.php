<?php

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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Phase 5: raw posting text in, structured (unsaved) fields out. Declared
// before the apiResource below on principle — a static path shouldn't rely
// on not colliding with the resource's wildcard route, even though POST
// here doesn't actually overlap any apiResource-registered method.
Route::post('/job-applications/extract', [JobPostingExtractionController::class, 'store']);

// Phase 3: index/store/show/update/destroy, all under /api/job-applications.
// Not behind auth yet — that's Phase 6, deliberately last per the roadmap.
Route::apiResource('job-applications', JobApplicationController::class);
