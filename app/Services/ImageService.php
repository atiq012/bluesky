<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ImageService
{
    /**
     * Upload image to mapped folder and return DB web path.
     */
    public function uploadAgentImage(UploadedFile $image, string $fieldKey, ?string $oldDbPath = null): string
    {
        $folder = $this->resolveFolderByField($fieldKey);

        if ($oldDbPath) {
            $this->deleteByDbPath($oldDbPath);
        }

        $absoluteDir = rtrim($this->basePath(), '/') . '/' . $folder;
        $this->ensureWritableDirectory($absoluteDir);

        $extension = $this->resolveExtension($image->getMimeType() ?? '', $image->getClientOriginalExtension());
        $filename = now()->format('dmY-His') . '_' . uniqid() . '.' . $extension;
        $absoluteFile = $absoluteDir . '/' . $filename;

        // Fallback mode: server missing GD extension -> save original image without resize/compress.
        if (! $this->canProcessWithGd()) {
            File::put($absoluteFile, File::get($image->getRealPath()));
            return rtrim(config('agent_uploads.db_public_prefix', '/uploads/agents'), '/') . '/' . $folder . '/' . $filename;
        }

        $source = $this->createImageResource($image->getRealPath(), $image->getMimeType() ?? '');
        if (! $source) {
            throw new \RuntimeException('Unsupported or invalid image uploaded.');
        }

        $resized = $this->resizeImageResource($source, 1600, 1600);
        imagedestroy($source);

        $quality = 85;
        $maxSize = 300 * 1024; // 300KB
        $jpegBinary = $this->encodeJpegBinary($resized, $quality);
        while (strlen($jpegBinary) > $maxSize && $quality > 35) {
            $quality -= 5;
            $jpegBinary = $this->encodeJpegBinary($resized, $quality);
        }

        File::put($absoluteFile, $jpegBinary);
        imagedestroy($resized);

        return rtrim(config('agent_uploads.db_public_prefix', '/uploads/agents'), '/') . '/' . $folder . '/' . $filename;
    }

    public function deleteByDbPath(?string $dbPath): bool
    {
        if (!$dbPath) {
            return false;
        }

        $relativePath = ltrim(preg_replace('#^/?uploads/agents/#', '', $dbPath), '/');
        $absolutePath = rtrim($this->basePath(), '/') . '/' . $relativePath;

        if (File::exists($absolutePath)) {
            return File::delete($absolutePath);
        }

        return false;
    }

    public function resolveAttachmentTypeByField(string $fieldKey): string
    {
        return match ($fieldKey) {
            'tradeFiles' => 'trade_licence_img',
            'cacFiles' => 'ca_img',
            'iataFiles' => 'iata_img',
            'hajjFiles' => 'hajj_licence_img',
            'tinFiles' => 'tin_img',
            'nidFiles' => 'nid_img',
            default => 'agent_img',
        };
    }

    public function basePath(): string
    {
        return rtrim((string) config('agent_uploads.base_path'), '/');
    }

    /**
     * @return list<string>
     */
    public function requiredSubdirectories(): array
    {
        return [
            'agency_img',
            'trade_licence_img',
            'ca_img',
            'iata_img',
            'hajj_licence_img',
            'tin_img',
            'nid_img',
            'misc',
        ];
    }

    public function ensureWritableDirectory(string $absoluteDir): void
    {
        if (! File::exists($absoluteDir)) {
            if (! @File::makeDirectory($absoluteDir, 0775, true) && ! File::isDirectory($absoluteDir)) {
                throw new \RuntimeException("Failed to create upload directory: {$absoluteDir}");
            }
        }

        if (! File::isDirectory($absoluteDir)) {
            throw new \RuntimeException("Upload path is not a directory: {$absoluteDir}");
        }

        if (! is_writable($absoluteDir)) {
            @chmod($absoluteDir, 0775);
        }

        $testFile = $absoluteDir . '/.write_test_' . uniqid('', true);
        if (@file_put_contents($testFile, '1') === false) {
            throw new \RuntimeException(
                "Upload directory is not writable by the web server: {$absoluteDir}. " .
                    'Run `php artisan agent-uploads:ensure` on the server or fix ownership/ACL for the PHP user.'
            );
        }

        @unlink($testFile);
    }

    private function resolveFolderByField(string $fieldKey): string
    {
        return match ($fieldKey) {
            'logo' => 'agency_img',
            'tradeFiles' => 'trade_licence_img',
            'cacFiles' => 'ca_img',
            'iataFiles' => 'iata_img',
            'hajjFiles' => 'hajj_licence_img',
            'tinFiles' => 'tin_img',
            'nidFiles' => 'nid_img',
            default => 'misc',
        };
    }

    private function createImageResource(string $path, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => \imagecreatefromjpeg($path),
            'image/png' => \imagecreatefrompng($path),
            'image/webp' => \function_exists('imagecreatefromwebp') ? \imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function resizeImageResource($source, int $maxWidth, int $maxHeight)
    {
        $srcWidth = \imagesx($source);
        $srcHeight = \imagesy($source);

        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $targetWidth = (int) round($srcWidth * $ratio);
        $targetHeight = (int) round($srcHeight * $ratio);

        $target = \imagecreatetruecolor($targetWidth, $targetHeight);
        \imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);

        return $target;
    }

    private function encodeJpegBinary($image, int $quality): string
    {
        \ob_start();
        \imagejpeg($image, null, $quality);
        return (string) \ob_get_clean();
    }

    private function canProcessWithGd(): bool
    {
        return \function_exists('imagecreatefromjpeg')
            && \function_exists('imagecreatefrompng')
            && \function_exists('imagecreatetruecolor')
            && \function_exists('imagecopyresampled')
            && \function_exists('imagejpeg');
    }

    private function resolveExtension(string $mimeType, ?string $originalExtension): string
    {
        if ($this->canProcessWithGd()) {
            return 'jpg';
        }

        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => strtolower($originalExtension ?: 'jpg'),
        };
    }
}
