<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Authenticated lecturer capabilities required by Academic Delivery reads.
 *
 * Delivery depends on this contract rather than the Faculty Workforce model.
 */
interface LecturerTeachingActor extends Authenticatable
{
    public function lecturerCampusId(): int;

    public function lecturerUserId(): int;

    public function classSessions(): HasMany;

    public function courseOfferings(): HasMany;
}
