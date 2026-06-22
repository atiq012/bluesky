<?php

namespace App\Services\SearchV2\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait PersistsTravelportResponseFile
{
    // subdir: '' = response/, 'price', 'booking'
    protected function persistTravelportResponseFile(string $subdir, array $providerResponse, ?string $requestId = null): array
    {
        $requestId = $requestId ?? (string) Str::uuid();
        $baseDir   = storage_path('app/response' . ($subdir !== '' ? '/' . $subdir : ''));
        $dailyDir  = now()->format('dmy');
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $dailyDir;

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $fileName     = now()->format('Ymd_His_u') . '_' . $requestId . '.json';
        $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        $encoded      = json_encode($providerResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        File::put($absolutePath, $encoded);

        $relativePrefix = 'response' . ($subdir !== '' ? '/' . $subdir : '');
        $relativePath   = $relativePrefix . '/' . $dailyDir . '/' . $fileName;
        $sizeBytes      = strlen((string) $encoded);

        return [$relativePath, $sizeBytes];
    }
}
