<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Exports\BatchChargePreviewExport;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
});

it('downloads the batch charge preview as an Excel workbook', function () {
    grantFinance($this->user, ['view_finance_batch_studio', 'create_finance_charges', 'view_finance_all_campus'], $this->campus);
    Excel::fake();

    $scope = ['filters' => []];
    $line = new BatchPreviewLine(
        key: 'charge:major:student:1:semester:'.$this->semester->id,
        hashPayload: [],
        display: [
            'student_id' => 'SV001',
            'label' => 'Nguyễn Văn A',
            'fee_category' => 'major',
            'diff' => 'create',
            'gross' => 45_000_000,
            'scholarship_name' => 'Học bổng Tài năng',
            'scholarship_type' => 'percentage',
            'scholarship_raw_value' => 20,
            'scholarship_amount' => 9_000_000,
            'voucher_codes' => [],
            'voucher_amount' => 0,
            'discount' => 9_000_000,
            'net' => 36_000_000,
            'reason' => null,
        ],
    );

    $assembler = Mockery::mock(AssembleBatchChargePreviewQuery::class);
    $assembler->shouldReceive('normalizeScope')
        ->once()
        ->with('major', $this->semester->id, [])
        ->andReturn($scope);
    $assembler->shouldReceive('handle')
        ->once()
        ->with('major', $this->semester->id, $scope, null)
        ->andReturn(['lines' => [$line], 'summary' => []]);
    app()->instance(AssembleBatchChargePreviewQuery::class, $assembler);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.charges.export', [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ]))
        ->assertOk();

    Excel::assertDownloaded(
        'batch-charge-preview-major-'.$this->semester->id.'.xlsx',
        function (BatchChargePreviewExport $export): bool {
            $row = $export->array()[0];

            return $row[1] === 'SV001'
                && $row[5] === 45_000_000.0
                && $row[6] === 'Học bổng Tài năng'
                && $row[9] === 9_000_000.0
                && $row[13] === 36_000_000.0;
        },
    );
});

it('forbids a major charge export without the category permission', function () {
    grantFinance($this->user, ['view_finance_batch_studio'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.charges.export', [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
        ]))
        ->assertForbidden();
});
