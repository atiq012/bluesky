<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    public function handle(Request $request, Closure $next, string $except = ''): Response
    {
        if (! config('sanitize.enabled', true)) {
            return $next($request);
        }

        $skip = array_merge(
            config('sanitize.except', []),
            $except === '' ? [] : array_map('trim', explode(',', $except))
        );

        $original = $request->input();
        $cleaned = $this->cleanArray($original, $skip);

        if (config('sanitize.dry_run', false)) {
            $this->logDryRunDiff($original, $cleaned);
            return $next($request);
        }

        $request->merge($cleaned);

        return $next($request);
    }

    private function cleanArray(array $data, array $except, string $prefix = ''): array
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if ($this->isExcepted($path, $except)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->cleanArray($value, $except, $path);
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $clean = trim(strip_tags($value));
            $data[$key] = ($clean === '' && $value !== '') ? null : $clean;
        }

        return $data;
    }

    private function isExcepted(string $path, array $except): bool
    {
        foreach ($except as $rule) {
            if ($rule === $path) {
                return true;
            }

            if (str_ends_with($rule, '.*')) {
                $prefix = substr($rule, 0, -2);
                if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function logDryRunDiff(array $original, array $cleaned, string $prefix = ''): void
    {
        foreach ($original as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $this->logDryRunDiff($value, $cleaned[$key] ?? [], $path);
                continue;
            }

            $newValue = $cleaned[$key] ?? $value;
            if ($newValue !== $value) {
                Log::info('sanitize.dry_run', ['field' => $path, 'before' => $value, 'after' => $newValue]);
            }
        }
    }
}
