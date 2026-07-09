<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Dng\ListDngWebhookEventsQuery;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'DNG']);
    $this->otherCampus = Campus::factory()->create(['code' => 'OTH']);
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn([
            'view_finance_dng_payment_requests',
            'view_finance_dng_webhook_events',
            'view_finance_payments',
        ]);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

function createDngStudent(object $context, string $studentCode, Campus $campus): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($context->program)
        ->state([
            'student_id' => $studentCode,
            'full_name' => "Student {$studentCode}",
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

function seedDngRequestWithEvent(object $context): array
{
    $student = createDngStudent($context, 'DNG001', $context->campus);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'external_ref' => 'PAY-DNG-001',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $request = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => $context->campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'tuition',
        'description' => 'Existing tuition collection request',
        'item_id' => 'ITEM-DNG-001',
        'amount' => 1000000,
        'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        'dng_transaction_id' => 'TX-001',
        'dng_payment_id' => 'PAY-DNG-001',
        'payment_id' => $payment->id,
        'push_payload' => ['StudentId' => $student->student_id],
        'push_response' => ['Code' => 200],
        'qr_payload' => ['QrCode' => 'abc'],
        'last_callback_payload' => ['PaymentId' => 'PAY-DNG-001'],
        'paid_at' => now(),
    ]);

    $event = DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-DNG-001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        'payload_hash' => hash('sha256', 'event-1'),
        'headers' => ['x-test' => '1'],
        'payload' => [
            'CampusCode' => $context->campus->code,
            'StudentId' => $student->student_id,
            'PaymentId' => 'PAY-DNG-001',
            'Amount' => '1000000',
            'InvoiceSerialNumber' => 'INV-001',
        ],
        'is_valid_checksum' => true,
        'processing_status' => DngWebhookEvent::STATUS_PROCESSED,
        'processed_at' => now(),
        'dng_payment_request_id' => $request->id,
    ]);

    return [$student, $payment, $request, $event];
}

it('renders the dng payment request index page', function () {
    [, , $request] = seedDngRequestWithEvent($this);

    $response = actingAs($this->user)->get(route('finance.dng.payment-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/DngPaymentRequests/Index')
        ->has('items.data', 1)
        ->where('items.data.0.id', $request->id)
        ->where('items.data.0.description', 'Existing tuition collection request')
        ->where('items.data.0.student_code', 'DNG001')
        ->where('items.data.0.payment.id', $request->payment_id)
        ->where('stats.bridged_count', 1)
    );
});

it('renders the dng payment request detail page', function () {
    [, $payment, $request, $event] = seedDngRequestWithEvent($this);

    $response = actingAs($this->user)->get(route('finance.dng.payment-requests.show', $request->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/DngPaymentRequests/Show')
        ->where('request.id', $request->id)
        ->where('request.description', 'Existing tuition collection request')
        ->where('request.payment.id', $payment->id)
        ->has('request.webhook_events', 1)
        ->where('request.webhook_events.0.id', $event->id)
        ->where('request.payloads.push_response.Code', 200)
    );
});

it('lists dng payment requests by student campus even when dng campus code differs from internal campus code', function () {
    $student = createDngStudent($this, 'DNG-CAMPUS-MAP', $this->campus);

    $request = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $student->student_id,
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-CAMPUS-MAP',
        'amount' => 250000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-CAMPUS-MAP',
    ]);

    $response = actingAs($this->user)->get(route('finance.dng.payment-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/DngPaymentRequests/Index')
        ->has('items.data', 1)
        ->where('items.data.0.id', $request->id)
        ->where('items.data.0.campus_code', 'FAUHN')
        ->where('items.data.0.student_code', 'DNG-CAMPUS-MAP')
    );
});

it('renders the dng webhook event index page', function () {
    [, , $request, $event] = seedDngRequestWithEvent($this);

    $response = actingAs($this->user)->get(route('finance.dng.webhook-events.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/DngWebhookEvents/Index')
        ->has('items.data', 1)
        ->where('items.data.0.id', $event->id)
        ->where('items.data.0.linked_request.id', $request->id)
        ->where('stats.processed_count', 1)
    );
});

it('renders the dng webhook event detail page', function () {
    [, $payment, $request, $event] = seedDngRequestWithEvent($this);

    $response = actingAs($this->user)->get(route('finance.dng.webhook-events.show', $event->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/DngWebhookEvents/Show')
        ->where('event.id', $event->id)
        ->where('event.request.id', $request->id)
        ->where('event.request.payment.id', $payment->id)
        ->where('event.payload_facts.PaymentId', 'PAY-DNG-001')
    );
});

it('lists webhook events across campuses including orphan payloads', function () {
    seedDngRequestWithEvent($this);

    $otherStudent = createDngStudent($this, 'OTH001', $this->otherCampus);

    $otherRequest = DngPaymentRequest::create([
        'student_id' => $otherStudent->id,
        'campus_code' => $this->otherCampus->code,
        'student_code' => $otherStudent->student_id,
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-OTH-001',
        'amount' => 500000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-OTH-001',
    ]);

    DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-OTH-001',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => hash('sha256', 'event-oth-linked'),
        'headers' => [],
        'payload' => [
            'CampusCode' => $this->otherCampus->code,
            'StudentId' => $otherStudent->student_id,
            'PaymentId' => 'PAY-OTH-001',
            'Amount' => '500000',
        ],
        'is_valid_checksum' => true,
        'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
        'dng_payment_request_id' => $otherRequest->id,
    ]);

    DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-ORPHAN-DNG',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => hash('sha256', 'event-orphan-dng'),
        'headers' => [],
        'payload' => [
            'CampusCode' => $this->campus->code,
            'StudentId' => 'DNG-ORPHAN',
            'PaymentId' => 'PAY-ORPHAN-DNG',
            'Amount' => '200000',
        ],
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_FAILED_TERMINAL,
        'error_category' => DngWebhookEvent::ERROR_CATEGORY_NOT_FOUND,
    ]);

    DngWebhookEvent::create([
        'dng_payment_id' => 'PAY-ORPHAN-OTH',
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_WITHOUT_INVOICE,
        'payload_hash' => hash('sha256', 'event-orphan-oth'),
        'headers' => [],
        'payload' => [
            'CampusCode' => $this->otherCampus->code,
            'StudentId' => 'OTH-ORPHAN',
            'PaymentId' => 'PAY-ORPHAN-OTH',
            'Amount' => '200000',
        ],
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_FAILED_TERMINAL,
        'error_category' => DngWebhookEvent::ERROR_CATEGORY_NOT_FOUND,
    ]);

    $result = app(ListDngWebhookEventsQuery::class)->handle(Request::create('/finance/dng/webhook-events', 'GET'));
    $items = collect($result['items']->items());

    expect($items->pluck('dng_payment_id')->all())
        ->toContain('PAY-DNG-001')
        ->toContain('PAY-ORPHAN-DNG')
        ->toContain('PAY-OTH-001')
        ->toContain('PAY-ORPHAN-OTH');
});
