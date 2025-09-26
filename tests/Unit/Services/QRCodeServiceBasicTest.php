<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\QRCodeService;

class QRCodeServiceBasicTest extends TestCase
{
    protected QRCodeService $qrCodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qrCodeService = new QRCodeService();
    }

    public function test_validates_qr_code_format()
    {
        $validCodes = [
            'EVTABCDEFGHIJKLMNOPQRSTUVWXYZ123456',
            'EVT1234567890ABCDEFGHIJKLMNOPQRSTUV',
        ];

        $invalidCodes = [
            'INVALID',
            'EVT123', // Too short
            'NOTEVTABCDEFGHIJKLMNOPQRSTUVWXYZ123456', // Wrong prefix
            'EVTABCDEFGHIJKLMNOPQRSTUVWXYZ1234567', // Too long
        ];

        foreach ($validCodes as $code) {
            $this->assertTrue($this->qrCodeService->isValidQRCodeFormat($code));
        }

        foreach ($invalidCodes as $code) {
            $this->assertFalse($this->qrCodeService->isValidQRCodeFormat($code));
        }
    }

    public function test_can_generate_qr_code_svg()
    {
        $qrCode = 'EVTTESTCODE123456789012345678901';

        $svgData = $this->qrCodeService->generateQRCodeSVG($qrCode);

        $this->assertIsString($svgData);
        $this->assertStringContainsString('<svg', $svgData);
    }

    public function test_can_generate_qr_code_image()
    {
        $qrCode = 'EVTTESTCODE123456789012345678901';

        $imageData = $this->qrCodeService->generateQRCodeImage($qrCode);

        $this->assertIsString($imageData);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $imageData);
    }

    public function test_can_generate_custom_qr_code()
    {
        $customData = [
            'event_id' => 123,
            'type' => 'checkin',
            'timestamp' => now()->timestamp
        ];

        $qrCodeSvg = $this->qrCodeService->generateCustomQRCode($customData);

        $this->assertIsString($qrCodeSvg);
        $this->assertStringContainsString('<svg', $qrCodeSvg);
    }

    public function test_can_generate_styled_qr_code()
    {
        $qrCode = 'EVTSTYLEDTEST123456789012345678901';
        $options = [
            'size' => 300,
            'eye_style' => 'circle',
            'color' => '#ff0000',
            'background_color' => '#ffffff'
        ];

        $styledQrCode = $this->qrCodeService->generateStyledQRCode($qrCode, $options);

        $this->assertIsString($styledQrCode);
        $this->assertStringContainsString('<svg', $styledQrCode);
    }
}
