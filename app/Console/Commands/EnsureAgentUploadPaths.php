<?php

namespace App\Console\Commands;

use App\Services\ImageService;
use Illuminate\Console\Command;

class EnsureAgentUploadPaths extends Command
{
    protected $signature = 'agent-uploads:ensure';

    protected $description = 'Create agent upload directories and verify the web server can write to them';

    public function handle(ImageService $imageService): int
    {
        $basePath = $imageService->basePath();
        $this->info("Agent upload base path: {$basePath}");

        try {
            foreach ($imageService->requiredSubdirectories() as $folder) {
                $absoluteDir = $basePath . '/' . $folder;
                $imageService->ensureWritableDirectory($absoluteDir);
                $this->line("OK: {$absoluteDir}");
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Server fix (run as root or account owner):');
            $this->line('  sudo mkdir -p ' . $basePath . '/{agency_img,trade_licence_img,ca_img,iata_img,hajj_licence_img,tin_img,nid_img,misc}');
            $this->line('  sudo chown -R devblues:dev2blue ' . dirname($basePath));
            $this->line('  sudo chmod -R 2775 ' . dirname($basePath));
            $this->line('  sudo setfacl -R -m u:dev2blue:rwx ' . dirname($basePath));
            $this->line('  sudo setfacl -R -d -m u:dev2blue:rwx ' . dirname($basePath));

            return self::FAILURE;
        }

        $this->info('Agent upload directories are writable.');

        return self::SUCCESS;
    }
}
