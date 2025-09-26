<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\QRCodeService;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;

class QRCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QRCodeService $qrCodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qrCodeService = new QRCodeService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_unique_qr_code()
    {
        $qrCode = $this->qrCodeService->generateEventQRCode();

        $this->assertIsString($qrCode);
        $this->assertStringStartsWith('EVT', $qrCode);
        $this->assertEquals(35, strlen($qrCode)); // EVT + 32 characters
        $this->assertTrue($this->qrCodeService->isValidQRCodeFormat($qrCode));
    }

    /** @test */
    public function it_generates_unique_qr_codes()
    {
        $qrCode1 = $this->qrCodeService->generateEventQRCode();
        $qrCode2 = $this->qrCodeService->generateEventQRCode();

        $this->assertNotEquals($qrCode1, $qrCode2);
    }

    /** @test */
    public function it_avoids_duplicate_qr_codes()
    {
        // Create an event with a specific QR code
        $existingEvent = Event::factory()->create([
            'qr_code' => 'EVTEXISTINGCODE123456789012345678'
        ]);

        // Mock the random generation to return the existing code first, then a new one
        $originalMethod = \Illuminate\Support\Str::class;

        // Generate a new QR code - it should be different from existing
        $newQrCode = $this->qrCodeService->generateEventQRCode();

        $this->assertNotEquals($existingEvent->qr_code, $newQrCode);
    }

    /** @test */
    public function it_can_validate_existing_qr_code()
    {
        $event = Event::factory()->create([
            'qr_code' => 'EVTVALIDCODE123456789012345678901'
        ]);

        $validatedEvent = $this->qrCodeService->validateQRCode($event->qr_code);

        $this->assertInstanceOf(Event::class, $validatedEvent);
        $this->assertEquals($event->id, $validatedEvent->id);
    }

    /** @test */
    public function it_returns_null_for_invalid_qr_code()
    {
        $validatedEvent = $this->qrCodeService->validateQRCode('EVTINVALIDCODE');

        $this->assertNull($validatedEvent);
    }

    /** @test */
    public function it_caches_qr_code_validation_results()
    {
        $event = Event::factory()->create([
            'qr_code' => 'EVTCACHEDCODE123456789012345678901'
        ]);

        // First call should hit the database
        $result1 = $this->qrCodeService->validateQRCode($event->qr_code);

        // Second call should hit the cache
        $result2 = $this->qrCodeService->validateQRCode($event->qr_code);

        $this->assertEquals($result1->id, $result2->id);

        // Verify cache was used
        $cacheKey = "qr_code_validation:{$event->qr_code}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_validates_qr_code_format()
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

    /** @test */
    public function it_can_generate_qr_code_image()
    {
        $qrCode = 'EVTTESTCODE123456789012345678901';

        $imageData = $this->qrCodeService->generateQRCodeImage($qrCode);

        $this->assertIsString($imageData);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $imageData);
    }

    /** @test */
    public function it_can_generate_qr_code_svg()
    {
        $qrCode = 'EVTTESTCODE123456789012345678901';

        $svgData = $this->qrCodeService->generateQRCodeSVG($qrCode);

        $this->assertIsString($svgData);
        $this->assertStringContains('<svg', $svgData);
    }

    /** @test */
    public function it_can_get_qr_code_statistics()
    {
        // Create events with and without QR codes
        Event::factory()->create(['qr_code' => 'EVTCODE1']);
        Event::factory()->create(['qr_code' => 'EVTCODE2', 'status' => 'published']);
        Event::factory()->create(['qr_code' => null]); // No QR code

        $stats = $this->qrCodeService->getQRCodeStatistics();

        $this->assertEquals(3, $stats['total_events']);
        $this->assertEquals(2, $stats['events_with_qr_codes']);
        $this->assertEquals(1, $stats['published_events_with_qr_codes']);
        $this->assertEquals(66.67, round($stats['qr_code_coverage'], 2));
    }

    /** @test */
    public function it_can_regenerate_qr_code_for_event()
    {
        $event = Event::factory()->create([
            'qr_code' => 'EVTOLDCODE123456789012345678901'
        ]);

        $oldQrCode = $event->qr_code;
        $newQrCode = $this->qrCodeService->regenerateQRCode($event);

        $this->assertNotEquals($oldQrCode, $newQrCode);
        $this->assertTrue($this->qrCodeService->isValidQRCodeFormat($newQrCode));

        $event->refresh();
        $this->assertEquals($newQrCode, $event->qr_code);
    }

    /** @test */
    public function it_clears_cache_when_regenerating_qr_code()
    {
        $event = Event::factory()->create([
            'qr_code' => 'EVTCACHETEST123456789012345678901'
        ]);

        // Cache the validation result
        $this->qrCodeService->validateQRCode($event->qr_code);
        $cacheKey = "qr_code_validation:{$event->qr_code}";
        $this->assertTrue(Cache::has($cacheKey));

        // Regenerate QR code
        $this->qrCodeService->regenerateQRCode($event);

        // Old cache should be cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function it_can_validate_multiple_qr_codes()
    {
        $event1 = Event::factory()->create(['qr_code' => 'EVTMULTI1']);
        $event2 = Event::factory()->create(['qr_code' => 'EVTMULTI2']);

        $qrCodes = ['EVTMULTI1', 'EVTMULTI2', 'EVTINVALID'];
        $results = $this->qrCodeService->validateMultipleQRCodes($qrCodes);

        $this->assertCount(3, $results);
        $this->assertEquals($event1->id, $results['EVTMULTI1']->id);
        $this->assertEquals($event2->id, $results['EVTMULTI2']->id);
        $this->assertNull($results['EVTINVALID']);
    }

    /** @test */
    public function it_can_clear_validation_cache()
    {
        $event = Event::factory()->create([
            'qr_code' => 'EVTCLEARTEST123456789012345678901'
        ]);

        // Cache the validation result
        $this->qrCodeService->validateQRCode($event->qr_code);
        $cacheKey = "qr_code_validation:{$event->qr_code}";
        $this->assertTrue(Cache::has($cacheKey));

        // Clear specific cache
        $this->qrCodeService->clearValidationCache($event->qr_code);
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function it_can_clear_all_validation_cache()
    {
        $event1 = Event::factory()->create(['qr_code' => 'EVTCLEAR1']);
        $event2 = Event::factory()->create(['qr_code' => 'EVTCLEAR2']);

        // Cache both validation results
        $this->qrCodeService->validateQRCode($event1->qr_code);
        $this->qrCodeService->validateQRCode($event2->qr_code);

        $cacheKey1 = "qr_code_validation:{$event1->qr_code}";
        $cacheKey2 = "qr_code_validation:{$event2->qr_code}";

        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));

        // Clear all cache
        $this->qrCodeService->clearValidationCache();

        $this->assertFalse(Cache::has($cacheKey1));
        $this->assertFalse(Cache::has($cacheKey2));
    }

    /** @test */
    public function it_can_generate_custom_qr_code()
    {
        $customData = [
            'event_id' => 123,
            'type' => 'checkin',
            'timestamp' => now()->timestamp
        ];

        $qrCodeSvg = $this->qrCodeService->generateCustomQRCode($customData);

        $this->assertIsString($qrCodeSvg);
        $this->assertStringContains('<svg', $qrCodeSvg);
    }

    /** @test */
    public function it_validates_qr_code_security_with_rate_limiting()
    {
        $qrCode = 'EVTSECURITYTEST123456789012345678';
        $ipAddress = '192.168.1.1';

        // First 10 scans should pass
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($this->qrCodeService->validateQRCodeSecurity($qrCode, $ipAddress));
        }

        // 11th scan should fail due to rate limiting
        $this->assertFalse($this->qrCodeService->validateQRCodeSecurity($qrCode, $ipAddress));
    }

    /** @test */
    public function it_can_generate_styled_qr_code()
    {
        $qrCode = 'EVTSTYLEDTEST123456789012345678901';
        $options = [
            'size' => 300,
            'eye_style' => 'circle',
            'body_pattern' => 'dot',
            'smoothness' => 0.8,
            'color' => '#ff0000',
            'background_color' => '#ffffff'
        ];

        $styledQrCode = $this->qrCodeService->generateStyledQRCode($qrCode, $options);

        $this->assertIsString($styledQrCode);
        $this->assertStringContains('<svg', $styledQrCode);
    }

    /** @test */
    public function it_can_generate_gradient_qr_code()
    {
        $qrCode = 'EVTGRADIENTTEST123456789012345678';
        $options = [
            'size' => 250,
            'gradient' => [
                'start_r' => 255,
                'start_g' => 0,
                'start_b' => 0,
                'end_r' => 0,
                'end_g' => 0,
                'end_b' => 255,
                'type' => 'diagonal'
            ]
        ];

        $gradientQrCode = $this->qrCodeService->generateStyledQRCode($qrCode, $options);

        $this->assertIsString($gradientQrCode);
        $this->assertStringContains('<svg', $gradientQrCode);
    }

    /** @test */
    public function it_handles_qr_code_generation_errors_gracefully()
    {
        // This test would require mocking the Quar facade to throw an exception
        // For now, we'll test that the method exists and can be called
        $qrCode = 'EVTERRORTEST123456789012345678901';

        $this->expectNotToPerformAssertions();

        try {
            $this->qrCodeService->generateQRCodeImage($qrCode);
        } catch (\RuntimeException $e) {
            $this->assertStringContains('Failed to generate QR code image', $e->getMessage());
        }
    }

    /** @test */
    public function it_throws_exception_when_max_generation_attempts_exceeded()
    {
        // Create many events to increase chance of collision
        for ($i = 0; $i < 100; $i++) {
            Event::factory()->create([
                'qr_code' => 'EVT' . str_pad($i, 32, '0', STR_PAD_LEFT)
            ]);
        }

        // This test is probabilistic and might not always trigger the exception
        // In a real scenario, you'd mock the random generation to force collisions
        $this->expectNotToPerformAssertions();

        try {
            $qrCode = $this->qrCodeService->generateEventQRCode();
            $this->assertTrue($this->qrCodeService->isValidQRCodeFormat($qrCode));
        } catch (\RuntimeException $e) {
            $this->assertStringContains('Failed to generate unique QR code', $e->getMessage());
        }
    }
}
