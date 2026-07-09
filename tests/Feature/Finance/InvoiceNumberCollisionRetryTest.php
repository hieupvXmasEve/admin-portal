<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/DataGuards/guard_fixtures.php';

/**
 * FIN-13: invoice_number is unique. The generator can collide for two invoices
 * created in the same second for the same student, so createInvoiceForSemester
 * retries with a freshly generated number instead of surfacing a 500.
 */
function retryAction(array $numbers): object
{
    return new class(app(InvoiceGenerationService::class), $numbers) extends CreateFinanceChargeAction
    {
        public int $calls = 0;

        /** @param array<int, string> $scriptedNumbers */
        public function __construct(InvoiceGenerationService $service, private array $scriptedNumbers)
        {
            parent::__construct($service);
        }

        public function makeInvoice(int $studentId, int $semesterId): StudentInvoice
        {
            return $this->createInvoiceForSemester($studentId, $semesterId);
        }

        protected function generateInvoiceNumber(int $studentId, int $semesterId): string
        {
            $number = $this->scriptedNumbers[$this->calls] ?? ('INV-FALLBACK-'.$this->calls);
            $this->calls++;

            return $number;
        }
    };
}

it('retries with a new number when the first generated number collides', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    StudentInvoice::query()->create([
        'invoice_number' => 'INV-COLLIDE',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $action = retryAction(['INV-COLLIDE', 'INV-WINNER']);
    $invoice = $action->makeInvoice($student->id, $semester->id);

    expect($invoice->invoice_number)->toBe('INV-WINNER')
        ->and($action->calls)->toBe(2);
});

it('gives up after the max attempts when collisions never clear', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    StudentInvoice::query()->create([
        'invoice_number' => 'INV-COLLIDE',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $action = retryAction(array_fill(0, 10, 'INV-COLLIDE'));

    expect(fn () => $action->makeInvoice($student->id, $semester->id))
        ->toThrow(QueryException::class);
    expect($action->calls)->toBe(5);
});
