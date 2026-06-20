<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Actions\RepointCourseRetakeRegistrationChargeAction;
use Illuminate\Console\Command;

class RepointRetakeRegistrationChargeCommand extends Command
{
    protected $signature = 'academic:repoint-retake-registration-charge
        {student_code : Student code (e.g. AUH113129)}
        {finance_charge_id : Active retake_fee charge id to use}';

    protected $description = 'Point a course-retake registration and paid HL DNG bridge at a different active retake_fee charge';

    public function handle(RepointCourseRetakeRegistrationChargeAction $action): int
    {
        $result = $action->run(
            studentCode: (string) $this->argument('student_code'),
            financeChargeId: (int) $this->argument('finance_charge_id'),
        );

        $this->info(sprintf(
            'Registration #%d now uses charge #%d (was #%s). Updated %d HL DNG request(s).',
            $result['registration_id'],
            $result['finance_charge_id'],
            $result['previous_charge_id'] ?? 'none',
            $result['dng_requests_updated'],
        ));

        return self::SUCCESS;
    }
}