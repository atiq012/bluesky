<?php

namespace App\Services\SearchV2;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TravelportTokenService
{
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $config = config('services.travelport_v2');
        $cacheKey = $config['token_cache_key'] ?? 'travelport_v2_access_token';
        $bufferSeconds = (int) ($config['token_refresh_buffer_seconds'] ?? 60);

        if (!$forceRefresh) {
            $cachedToken = Cache::get($cacheKey);
            if (!empty($cachedToken['access_token']) && !empty($cachedToken['expires_at'])) {
                $expiresAt = Carbon::parse($cachedToken['expires_at']);
                if ($expiresAt->gt(now()->addSeconds($bufferSeconds))) {
                    return $cachedToken['access_token'];
                }
            }
        }

        return $this->requestAndCacheToken($cacheKey);
    }

    private function requestAndCacheToken(string $cacheKey): string
    {
        $config = config('services.travelport_v2');

        $required = [
            'auth_url',
            'grant_type',
            'username',
            'password',
            'client_id',
            'client_secret',
            'access_group',
        ];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("Missing travelport_v2 config: {$key}");
            }
        }

        $response = Http::asForm()
            ->timeout(30)
            ->acceptJson()
            ->post($config['auth_url'], [
                'grant_type' => $config['grant_type'],
                'username' => $config['username'],
                'password' => $config['password'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'access_group' => $config['access_group'],

            ]);

        if (!$response->successful()) {
            throw new Exception('Travelport token request failed: ' . $response->status());
        }

        $payload = $response->json();
        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        if (empty($accessToken) || $expiresIn <= 0) {
            throw new Exception('Travelport token payload missing access token or expiry.');
        }

        $expiresAt = now()->addSeconds($expiresIn);

        Cache::put($cacheKey, [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return $accessToken;
    }
}
