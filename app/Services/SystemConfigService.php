<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SystemConfigService
{
    private const CONFIG_FILE = 'system_config.json';
    private const CACHE_KEY = 'system_config';

    /**
     * Get system configuration
     */
    public function getConfig(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return $this->loadConfigFromFile();
        });
    }

    /**
     * Get specific configuration value
     */
    public function get(string $key, $default = null)
    {
        $config = $this->getConfig();
        return data_get($config, $key, $default);
    }

    /**
     * Update configuration
     */
    public function updateConfig(array $data): bool
    {
        $currentConfig = $this->getConfig();
        $updatedConfig = array_merge($currentConfig, $data);

        $success = Storage::put(self::CONFIG_FILE, json_encode($updatedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($success) {
            $this->refreshCache();
        }

        return $success;
    }

    /**
     * Upload file and update configuration
     */
    public function uploadFile(UploadedFile $file, string $configKey): array
    {
        // Define the target path based on config key
        $targetPath = $this->getTargetPathForConfigKey($configKey);
        
        if (!$targetPath) {
            throw new \InvalidArgumentException("Invalid config key for file upload: {$configKey}");
        }

        // Get the directory and filename
        $directory = dirname($targetPath);
        $filename = basename($targetPath);

        // Ensure the directory exists in public storage
        $publicDirectory = str_replace('/storage/', '', $directory);
        Storage::disk('public')->makeDirectory($publicDirectory);

        // Store the file with the exact filename, overwriting if exists
        $stored = Storage::disk('public')->putFileAs(
            $publicDirectory, 
            $file, 
            $filename
        );

        if (!$stored) {
            throw new \Exception('Failed to store uploaded file');
        }

        // Return success with the path and cache busting timestamp
        return [
            'success' => true,
            'path' => $targetPath,
            'stored_path' => '/storage/' . $stored,
            'cache_bust' => time()
        ];
    }

    /**
     * Get target file path for config key
     */
    private function getTargetPathForConfigKey(string $configKey): ?string
    {
        $config = $this->getConfig();
        
        return match($configKey) {
            'logo_full' => $config['logo_full'] ?? '/storage/branding/logo-full.png',
            'logo_text' => $config['logo_text'] ?? '/storage/branding/logo-text.svg',
            default => null
        };
    }

    /**
     * Clear configuration cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Refresh configuration cache
     */
    public function refreshCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->getConfig(); // Reload and cache the configuration
    }

    /**
     * Load configuration from file
     */
    private function loadConfigFromFile(): array
    {
        if (!Storage::exists(self::CONFIG_FILE)) {
            return $this->getDefaultConfig();
        }

        $content = Storage::get(self::CONFIG_FILE);
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Failed to parse system config JSON: ' . json_last_error_msg());
            return $this->getDefaultConfig();
        }

        return $config;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'app_name' => config('app.name'),
            'logo_full' => '/storage/branding/logo-full.png',
            'logo_text' => '/storage/branding/logo-text.svg',
            'copyright_text' => '© ' . date('Y') . ' Asia Vietnam University. All rights reserved.',
            'country' => 'Việt Nam'
        ];
    }
}
