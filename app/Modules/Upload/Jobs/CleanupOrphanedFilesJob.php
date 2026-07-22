<?php

declare(strict_types=1);

namespace App\Modules\Upload\Jobs;

use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOrphanedFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(private array $config = []) {}

    public function handle(UploadPlatform $uploadPlatform): void
    {
        $uploadPlatform->cleanup($this->config);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Upload cleanup job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'config' => $this->config,
        ]);
    }
}
