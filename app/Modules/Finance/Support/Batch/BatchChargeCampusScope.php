<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

use Illuminate\Validation\ValidationException;

final class BatchChargeCampusScope
{
    public function currentId(): int
    {
        $campusId = (int) session('current_campus_id');

        if ($campusId <= 0) {
            throw ValidationException::withMessages([
                'campus_id' => 'Vui lòng chọn campus trước khi thao tác sinh phí hàng loạt.',
            ]);
        }

        return $campusId;
    }

    public function assertCurrent(int $expectedCampusId): int
    {
        $currentCampusId = $this->currentId();

        if ($expectedCampusId <= 0 || $expectedCampusId !== $currentCampusId) {
            throw ValidationException::withMessages([
                'preview_token' => 'Campus hiện tại đã thay đổi từ lúc xem trước. Vui lòng xem trước lại.',
            ]);
        }

        return $currentCampusId;
    }
}
