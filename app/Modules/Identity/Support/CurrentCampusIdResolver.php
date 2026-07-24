<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use Illuminate\Http\Request;
use LogicException;

final class CurrentCampusIdResolver
{
    public function __construct(private readonly Request $request) {}

    public function resolve(): int
    {
        $campusId = $this->request->session()->get('current_campus_id');

        if ($campusId === null) {
            throw new LogicException('A campus must be selected before accessing campus-scoped user data.');
        }

        return (int) $campusId;
    }
}
