<?php

declare(strict_types=1);

namespace App\Console\Commands\Admissions;

use App\Services\Admissions\AdmissionsServiceAccountManager;
use Illuminate\Console\Command;

/**
 * Mints a scoped Sanctum token for the admissions-CRM service account so the
 * CRM can authenticate to the /api/v1 ingestion endpoints (ADR-0004).
 *
 * The plaintext token is shown once; only its hash is stored. Re-running mints
 * an additional token without revoking the previous ones.
 */
class MintAdmissionsIngestTokenCommand extends Command
{
    protected $signature = 'admissions:mint-ingest-token';

    protected $description = 'Mint a scoped Sanctum token for the admissions CRM ingestion service account';

    public function handle(AdmissionsServiceAccountManager $accounts): int
    {
        $token = $accounts->mintIngestToken();

        $this->info('Admissions ingestion token minted. Store it securely — it is shown only once:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
