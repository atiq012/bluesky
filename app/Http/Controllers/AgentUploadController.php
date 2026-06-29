<?php

namespace App\Http\Controllers;

use App\Services\ImageService;
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

        $absolutePath = $imageService->resolveAbsolutePath(
            rtrim((string) config('agent_uploads.db_public_prefix', '/uploads/agents'), '/') . '/' . $path
        );
        if ($absolutePath === null) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
