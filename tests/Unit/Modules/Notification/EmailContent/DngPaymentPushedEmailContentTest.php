<?php

declare(strict_types=1);

use App\Modules\Notification\EmailContent\Types\DngPaymentPushedEmailContent;

beforeEach(function () {
    $this->provider = new DngPaymentPushedEmailContent();
});

it('renders subject with semester code', function () {
    $subject = $this->provider->subject(['semester_code' => 'Fall 2024']);

    expect($subject)->toBe('[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí học kỳ Fall 2024');
});

it('renders subject without semester when semester_code is empty', function () {
    $subject = $this->provider->subject(['semester_code' => '']);

    expect($subject)->toBe('[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí');
});

it('renders subject without semester when semester_code is missing', function () {
    $subject = $this->provider->subject([]);

    expect($subject)->toBe('[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí');
});

it('renders html body with due_date rows when due_date is provided', function () {
    $html = $this->provider->htmlBody([
        'student_name' => 'Jane Smith',
        'student_code' => 'STU001',
        'semester_code' => 'Fall 2024',
        'program_name' => 'Introduction to Computer Science',
        'invoice_code' => 'STU001_123',
        'amount_formatted' => '25.000.000 VNĐ',
        'due_date' => '30/06/2025',
    ]);

    expect($html)
        ->toContain('Jane Smith STU001')
        ->toContain('Fall 2024')
        ->toContain('Introduction to Computer Science')
        ->toContain('STU001_123')
        ->toContain('25.000.000 VNĐ')
        ->toContain('30/06/2025')
        ->toContain('Hạn thanh toán:')
        ->toContain('Payment Deadline:');
});

it('renders html body without due_date rows when due_date is null', function () {
    $html = $this->provider->htmlBody([
        'student_name' => 'Jane Smith',
        'student_code' => 'STU001',
        'semester_code' => 'Fall 2024',
        'program_name' => 'CS',
        'invoice_code' => 'STU001_123',
        'amount_formatted' => '25.000.000 VNĐ',
        'due_date' => null,
    ]);

    expect($html)
        ->not->toContain('Hạn thanh toán:')
        ->not->toContain('Payment Deadline:');
});

it('returns null for textBody', function () {
    expect($this->provider->textBody([]))->toBeNull();
});

it('escapes html special characters in student name', function () {
    $html = $this->provider->htmlBody([
        'student_name' => '<script>alert(1)</script>',
        'student_code' => 'STU001',
    ]);

    expect($html)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});
