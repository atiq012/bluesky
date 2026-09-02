<?php

namespace App\Http\Middleware;

use App\Models\Agent\Agent;
use App\Models\APIManagement\ApiManagement;
use App\Services\AccessControl\AgencyApiAccessChecker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AgentApiAccessMiddleware
{
    public function __construct(
        private readonly AgencyApiAccessChecker $checker,
    ) {}

    public function handle(Request $request, Closure $next, string $apiKey): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // Sub-users link via users.agent_id; agents.user_id is owner-only
        $agencyId = Cache::remember("user_agency_id:v2:{$user->id}", 600, function () use ($user) {
            if (!empty($user->agent_id)) {
                return (int) $user->agent_id;
            }

            return Agent::where('user_id', $user->id)->value('id') ?? 0;
        });

        if (!$agencyId) {
            return $next($request);
        }

        $apiId = $this->resolveApiId($apiKey);
        if (!$apiId) {
            return $next($request);
        }

        if ($this->checker->isBlocked((int) $agencyId, $apiId)) {
            return response()->json([
                'status'  => false,
                'message' => 'API access not permitted for your account.',
            ], 403);
        }

        return $next($request);
    }

    // Route key e.g. travelport_v2 — DB name may be "Travelport  UAPI" (messy spaces)
    private function resolveApiId(string $apiKey): int
    {
        return (int) Cache::remember("api_management_key:v2:{$apiKey}", 3600, function () use ($apiKey) {
            $needle = $this->normalizeKey($apiKey);

            $exact = ApiManagement::where('name', $apiKey)->value('id');
            if ($exact) {
                return (int) $exact;
            }

            foreach (ApiManagement::query()->get(['id', 'name']) as $api) {
                $norm = $this->normalizeKey((string) $api->name);
                if ($norm === $needle) {
                    return (int) $api->id;
                }
                // Alias: travelport_v2 / travelport_uapi → any Travelport* row
                if (str_starts_with($needle, 'travelport') && str_contains($norm, 'travelport')) {
                    return (int) $api->id;
                }
            }

            return 0;
        });
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return str_replace([' ', '-'], '_', $value);
    }
}
