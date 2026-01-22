<?php

namespace App\Modules\Finance\Actions;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class StorePaymentImportAction
{
    public function run(array $data, int $userId): array
    {
        // Expects $data to be an array of validated row objects
        // Structure: [['student_id' => 1, 'amount' => 1000, 'paid_at' => '...', 'external_ref' => '...']]
        
        $count = 0;
        
        DB::transaction(function () use ($data, $userId, &$count) {
            foreach ($data as $row) {
                // Double check duplicate in case of race condition or re-submission
                if (!empty($row['external_ref']) && Payment::where('source', 'import')->where('external_ref', $row['external_ref'])->exists()) {
                    continue; 
                }

                Payment::create([
                    'student_id' => $row['student_id'],
                    'amount' => $row['amount'],
                    'method' => Payment::METHOD_IMPORT,
                    'source' => 'import',
                    'external_ref' => $row['external_ref'] ?? null,
                    'paid_at' => $row['paid_at'],
                    'status' => Payment::STATUS_COMPLETED,
                    'received_by_user_id' => $userId,
                    'notes' => $row['notes'] ?? null,
                    'raw_payload' => $row,
                ]);
                
                $count++;
            }
        });

        return ['imported_count' => $count];
    }
}
