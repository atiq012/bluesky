<?php

namespace App\Http\Controllers;

use App\Services\ImageService;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentUploadController extends Controller
{
    public function show(string $path, ImageService $imageService): BinaryFileResponse
    {
        if (str_contains($path, '..')) {
            abort(404);
        }

        $folder = strtok($path, '/');
        if (! $folder || ! in_array($folder, $imageService->requiredSubdirectories(), true)) {
            abort(404);
        }

        $absolutePath = rtrim($imageService->basePath(), '/') . '/' . $path;
        if (! File::isFile($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
