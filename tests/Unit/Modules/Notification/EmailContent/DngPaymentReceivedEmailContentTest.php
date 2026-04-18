<?php

declare(strict_types=1);

use App\Modules\Notification\EmailContent\Types\DngPaymentReceivedEmailContent;

beforeEach(function () {
    $this->provider = new DngPaymentReceivedEmailContent;
});

it('renders subject with semester code', function () {
    $subject = $this->provider->subject(['semester_code' => 'HK1 2024-2025']);

    expect($subject)->toBe('[Asia Việt Nam] Xác nhận thanh toán thành công - Học kỳ HK1 2024-2025');
});

it('renders subject without semester when semester_code is empty', function () {
    $subject = $this->provider->subject(['semester_code' => '']);

    expect($subject)->toBe('[Asia Việt Nam] Xác nhận thanh toán thành công');
});

it('renders subject without semester when semester_code is missing', function () {
    $subject = $this->provider->subject([]);

    expect($subject)->toBe('[Asia Việt Nam] Xác nhận thanh toán thành công');
});

it('renders html body with semester row', function () {
    $html = $this->provider->htmlBody([
        'student_name' => 'Nguyen Van A',
        'student_code' => 'SV001',
        'amount_formatted' => '10.000.000 VNĐ',
        'semester_code' => 'HK1 2024-2025',
        'paid_at' => '2026-04-18 10:00:00',
    ]);

    expect($html)
        ->toContain('Nguyen Van A SV001')
        ->toContain('10.000.000 VN')
        ->toContain('HK1 2024-2025')
        ->toContain('Semester:');
});

it('renders html body without semester rows when semester_code is empty', function () {
    $html = $this->provider->htmlBody([
        'student_name' => 'Nguyen Van A',
        'student_code' => 'SV001',
        'amount_formatted' => '10.000.000 VNĐ',
        'semester_code' => '',
        'paid_at' => null,
    ]);

    expect($html)
        ->toContain('Nguyen Van A SV001')
        ->not->toContain('Học kỳ:')
        ->not->toContain('Semester:');
});

it('returns null for text body', function () {
    expect($this->provider->textBody([]))->toBeNull();
});
