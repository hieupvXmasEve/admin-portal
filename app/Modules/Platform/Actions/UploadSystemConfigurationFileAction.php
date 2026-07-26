<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Shared\Contracts\Upload\FileUploadGateway;
use Illuminate\Http\UploadedFile;
use Throwable;

final class UploadSystemConfigurationFileAction
{
    /**
     * @param  array{file: UploadedFile, slot: string}  $data
     * @return array<string, mixed>
     */
    public static function run(array $data): array
    {
        $file = $data['file'];
        $slot = $data['slot'];
        $settingKey = self::settingKey($slot);
        $actorId = auth()->id();

        $upload = app(FileUploadGateway::class)->store(
            $file,
            'branding',
            is_int($actorId) ? $actorId : null,
            metadata: ['slot' => $slot],
        );

        try {
            $configuration = UpdateSystemConfigurationAction::run([$settingKey => $upload->id]);
        } catch (Throwable $exception) {
            app(FileUploadGateway::class)->delete($upload->id);

            throw $exception;
        }

        return [
            'slot' => $slot,
            'updated_keys' => array_keys($configuration),
        ];
    }

    private static function settingKey(string $slot): string
    {
        return match ($slot) {
            'logo_full' => 'logo_full_upload_id',
            'logo_text' => 'logo_text_upload_id',
            'favicon' => 'favicon_upload_id',
            'apple_touch_icon' => 'apple_touch_icon_upload_id',
            default => throw new \InvalidArgumentException('Unsupported branding slot.'),
        };
    }
}
