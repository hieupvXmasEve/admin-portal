<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload;

use Illuminate\Http\UploadedFile;

interface FileUploadGateway
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(
        UploadedFile $file,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = [],
    ): StoredUpload;

    public function urlFor(int $uploadId): string;

    public function linkToQueryReply(int $uploadId, int $ticketId, int $replyId): void;
}
