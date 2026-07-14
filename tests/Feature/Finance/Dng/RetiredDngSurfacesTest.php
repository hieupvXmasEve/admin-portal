<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('does not register retired DNG worklist routes', function (): void {
    get('/finance/operations/dng-worklist')->assertNotFound();
    post('/finance/operations/dng-worklist')->assertNotFound();
});

it('does not register retired bulk EGC generation APIs', function (): void {
    post('/api/v1/finance/operations/preview-charges')->assertNotFound();
    post('/api/v1/finance/operations/export-preview-charges')->assertNotFound();
    post('/api/v1/finance/operations/run-generate')->assertNotFound();
});
