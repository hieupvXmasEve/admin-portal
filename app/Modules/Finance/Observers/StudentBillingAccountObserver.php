<?php

declare(strict_types=1);

namespace App\Modules\Finance\Observers;

use App\Models\Student;
use App\Modules\Finance\Support\BillingAccountProvisioner;

/**
 * Auto-provisions exactly one Finance billing account when a Student is created.
 */
class StudentBillingAccountObserver
{
    public function created(Student $student): void
    {
        app(BillingAccountProvisioner::class)->forStudent((int) $student->id);
    }
}
