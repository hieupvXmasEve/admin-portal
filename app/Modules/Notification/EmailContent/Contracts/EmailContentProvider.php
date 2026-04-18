<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Contracts;

interface EmailContentProvider
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function subject(array $data): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function htmlBody(array $data): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function textBody(array $data): ?string;
}
