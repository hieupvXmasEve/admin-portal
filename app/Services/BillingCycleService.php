<?php

namespace App\Services;

use App\Models\BillingCycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingCycleService
{
    /**
     * Create a new billing cycle.
     *
     * @param array $data
     * @return BillingCycle
     * @throws ValidationException
     */
    public function createBillingCycle(array $data): BillingCycle
    {
        // Validate date sequence
        $this->validateDateSequence($data['start_date'], $data['end_date'], $data['due_date']);

        // Validate no overlapping cycles
        $this->validateNoOverlap($data['start_date'], $data['end_date']);

        return DB::transaction(function () use ($data) {
            return BillingCycle::create([
                'semester_id' => $data['semester_id'],
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'due_date' => $data['due_date'],
                'status' => 'draft',
            ]);
        });
    }

    /**
     * Activate a billing cycle.
     *
     * @param int $id
     * @return BillingCycle
     * @throws \Exception
     */
    public function activateBillingCycle(int $id): BillingCycle
    {
        $cycle = BillingCycle::findOrFail($id);

        if (!$cycle->isDraft()) {
            throw new \Exception('Only draft billing cycles can be activated.');
        }

        $cycle->activate();
        $cycle->refresh();

        return $cycle;
    }

    /**
     * Close a billing cycle.
     *
     * @param int $id
     * @return BillingCycle
     * @throws \Exception
     */
    public function closeBillingCycle(int $id): BillingCycle
    {
        $cycle = BillingCycle::findOrFail($id);

        if (!$cycle->isActive()) {
            throw new \Exception('Only active billing cycles can be closed.');
        }

        $cycle->close();
        $cycle->refresh();

        return $cycle;
    }

    /**
     * Update a billing cycle.
     *
     * @param int $id
     * @param array $data
     * @return BillingCycle
     * @throws ValidationException
     */
    public function updateBillingCycle(int $id, array $data): BillingCycle
    {
        $cycle = BillingCycle::findOrFail($id);

        if ($cycle->isClosed()) {
            throw new \Exception('Closed billing cycles cannot be updated.');
        }

        if ($cycle->isActive()) {
            if (!array_key_exists('name', $data)) {
                throw new \Exception('Name is required to update an active billing cycle.');
            }

            $payload = [
                'name' => $data['name'],
            ];

            return DB::transaction(function () use ($cycle, $payload) {
                $cycle->update($payload);

                return $cycle->fresh();
            });
        }

        // Validate date sequence if dates are being updated
        if (isset($data['start_date']) || isset($data['end_date']) || isset($data['due_date'])) {
            $startDate = $data['start_date'] ?? $cycle->start_date;
            $endDate = $data['end_date'] ?? $cycle->end_date;
            $dueDate = $data['due_date'] ?? $cycle->due_date;

            $this->validateDateSequence($startDate, $endDate, $dueDate);
            $this->validateNoOverlap($startDate, $endDate, $id);
        }

        return DB::transaction(function () use ($cycle, $data) {
            $cycle->update($data);

            return $cycle->fresh();
        });
    }

    /**
     * Delete a billing cycle.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteBillingCycle(int $id): bool
    {
        $cycle = BillingCycle::findOrFail($id);

        // Only allow deletion if cycle is in draft status
        if (!$cycle->isDraft()) {
            throw new \Exception('Only draft billing cycles can be deleted.');
        }

        return $cycle->delete();
    }

    /**
     * Validate that dates are in correct sequence.
     *
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     * @param string|Carbon $dueDate
     * @throws ValidationException
     */
    protected function validateDateSequence($startDate, $endDate, $dueDate): void
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $due = Carbon::parse($dueDate);

        if ($start->greaterThanOrEqualTo($end)) {
            throw ValidationException::withMessages([
                'end_date' => ['End date must be after start date.']
            ]);
        }

        if ($due->lessThan($start)) {
            throw ValidationException::withMessages([
                'due_date' => ['Due date cannot be before start date.']
            ]);
        }
    }

    /**
     * Validate that the billing cycle does not overlap with existing cycles.
     *
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     * @param int|null $excludeId
     * @throws ValidationException
     */
    protected function validateNoOverlap($startDate, $endDate, ?int $excludeId = null): void
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $query = BillingCycle::where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_date', '<=', $start)
                        ->where('end_date', '>=', $end);
                });
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => ['This billing cycle overlaps with an existing cycle.']
            ]);
        }
    }
}
