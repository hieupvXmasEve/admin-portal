<?php

declare(strict_types=1);

namespace App\Models;

/**
 * @deprecated Use \App\Modules\Upload\Models\UploadRecord instead. Kept for
 * backward compatibility until callers are swept to the new namespace.
 */
class_alias(\App\Modules\Upload\Models\UploadRecord::class, UploadRecord::class);
