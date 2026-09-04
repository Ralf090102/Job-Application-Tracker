<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards POST /api/auto-apply/ingest — a machine-to-machine endpoint n8n
 * calls, not the browser SPA, so Sanctum's cookie auth doesn't apply here.
 * A plain shared-secret header instead (JAT-Roadmap-AutoApply.md Phase 2).
 */
class VerifyAutoApplyIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.auto_apply.ingest_token');
        $provided = $request->header('X-Ingest-Token');

        if (! $expected || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid or missing ingest token.'], 401);
        }

        return $next($request);
    }
}
