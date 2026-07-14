<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dng_payment_requests', 'finance_charge_id')) {
            return;
        }

        $requests = DB::table('dng_payment_requests')
            ->whereNotNull('finance_charge_id')
            ->orderBy('id')
            ->select(['id', 'finance_charge_id', 'amount', 'payment_id'])
            ->get();

        // Resolve every legacy header graph before writing anything. A paid
        // request's payment applications are exact ledger evidence and win over
        // a stale header or pivot; otherwise the header must cover the request.
        $linksByRequestId = $requests->mapWithKeys(
            fn (object $request): array => [$request->id => $this->exactChargeLinksFor($request)],
        );

        DB::transaction(function () use ($linksByRequestId): void {
            foreach ($linksByRequestId as $requestId => $expectedLinks) {
                $existingLinks = DB::table('dng_payment_request_charges')
                    ->where('dng_payment_request_id', $requestId)
                    ->orderBy('finance_charge_id')
                    ->get(['finance_charge_id', 'amount']);
                if ($this->linksMatch($existingLinks->all(), $expectedLinks)) {
                    continue;
                }

                DB::table('dng_payment_request_charges')
                    ->where('dng_payment_request_id', $requestId)
                    ->delete();

                foreach ($expectedLinks as $link) {
                    DB::table('dng_payment_request_charges')->insertOrIgnore([
                        'dng_payment_request_id' => $requestId,
                        'finance_charge_id' => $link['finance_charge_id'],
                        'amount' => $link['amount'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
        });

        Schema::table('dng_payment_requests', function (Blueprint $table): void {
            $table->dropForeign(['finance_charge_id']);
            $table->dropColumn('finance_charge_id');
        });
    }

    /** Forward-only retirement: the removed source pointer is never recreated. */
    public function down(): void {}

    /**
     * @return list<array{finance_charge_id: int, amount: string}>
     */
    private function exactChargeLinksFor(object $request): array
    {
        if ($request->payment_id !== null) {
            $paymentLinks = $this->paymentChargeLinks($request->payment_id);
            $paymentTotal = $paymentLinks->reduce(
                static fn (Money $total, object $link): Money => $total->add(Money::vnd((string) $link->amount)),
                Money::zero(),
            );
            if ($paymentLinks->isNotEmpty()
                && $paymentTotal->minor_amount === Money::vnd((string) $request->amount)->minor_amount) {
                return $paymentLinks
                    ->map(static fn (object $link): array => [
                        'finance_charge_id' => (int) $link->finance_charge_id,
                        'amount' => Money::vnd((string) $link->amount)->amount,
                    ])
                    ->all();
            }
        }

        $headerRepresentsWholeRequest = DB::table('finance_charges')
            ->where('id', $request->finance_charge_id)
            ->where('amount', (string) $request->amount)
            ->exists();
        if (! $headerRepresentsWholeRequest) {
            throw new RuntimeException(
                "Cannot retire DNG header pointer for request {$request->id}: neither its payment applications nor its header provide an exact charge graph."
            );
        }

        return [[
            'finance_charge_id' => (int) $request->finance_charge_id,
            'amount' => Money::vnd((string) $request->amount)->amount,
        ]];
    }

    /** @return Collection<int, object> */
    private function paymentChargeLinks(int $paymentId): Collection
    {
        return DB::table('payment_applications as application')
            ->join('invoice_lines as line', 'line.id', '=', 'application.invoice_line_id')
            ->where('application.payment_id', $paymentId)
            ->groupBy('line.charge_id')
            ->havingRaw('SUM(application.amount) > 0')
            ->orderBy('line.charge_id')
            ->get([
                'line.charge_id as finance_charge_id',
                DB::raw('SUM(application.amount) as amount'),
            ]);
    }

    /** @param list<object> $existingLinks @param list<array{finance_charge_id: int, amount: string}> $expectedLinks */
    private function linksMatch(array $existingLinks, array $expectedLinks): bool
    {
        if (count($existingLinks) !== count($expectedLinks)) {
            return false;
        }

        foreach ($existingLinks as $index => $existingLink) {
            $expectedLink = $expectedLinks[$index];
            if ((int) $existingLink->finance_charge_id !== $expectedLink['finance_charge_id']
                || Money::vnd((string) $existingLink->amount)->minor_amount !== Money::vnd($expectedLink['amount'])->minor_amount) {
                return false;
            }
        }

        return true;
    }
};
