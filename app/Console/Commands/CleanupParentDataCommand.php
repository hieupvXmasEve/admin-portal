<?php

namespace App\Console\Commands;

use App\Models\ParentProfile;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupParentDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-parent-data {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up inconsistent parent data (Case 1: ParentProfile linked to student users, Case 2: Parent users without ParentProfile)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be saved.');
        }

        $this->cleanupCase1($dryRun);
        $this->cleanupCase2($dryRun);

        $this->info('Cleanup completed.');
    }

    /**
     * Case 1: Remove ParentProfile records linked to users whose type is "student"
     */
    private function cleanupCase1(bool $dryRun): void
    {
        $this->info('Checking Case 1: ParentProfile records linked to student users...');

        $invalidProfiles = ParentProfile::whereHas('user', function ($query) {
            $query->where('type', UserType::STUDENT);
        })->with('user')->get();

        if ($invalidProfiles->isEmpty()) {
            $this->comment('No invalid profiles found for Case 1.');
            return;
        }

        foreach ($invalidProfiles as $profile) {
            $this->warn("Found Case 1: ParentProfile ID {$profile->id} linked to student User: {$profile->user->email}");

            if (! $dryRun) {
                DB::transaction(function () use ($profile): void {
                    // Guardian relationship rows (Registry-owned) are left intact —
                    // they describe the person, not the portal grant, and stay
                    // valid even without an access grant behind them.
                    $grantCount = DB::table('guardian_access_grants')->where('parent_id', $profile->id)->count();

                    if ($grantCount > 0) {
                        $this->info("Detaching {$grantCount} students from ParentProfile ID {$profile->id}");
                        DB::table('guardian_access_grants')->where('parent_id', $profile->id)->delete();
                    }

                    // Legacy pivot hygiene: clear any remaining row even if no grant exists.
                    DB::table('parent_student')->where('parent_id', $profile->id)->delete();

                    $profile->delete();
                });
                $this->info("Deleted ParentProfile ID {$profile->id}");
            }
        }
    }

    /**
     * Case 2: Create ParentProfile records for users with type "parent" that lack one
     */
    private function cleanupCase2(bool $dryRun): void
    {
        $this->info('Checking Case 2: Parent users without ParentProfile...');

        $usersWithoutProfile = User::where('type', UserType::PARENT)
            ->whereDoesntHave('parentProfile')
            ->get();

        if ($usersWithoutProfile->isEmpty()) {
            $this->comment('No users found for Case 2.');
            return;
        }

        foreach ($usersWithoutProfile as $user) {
            $this->warn("Found Case 2: User ID {$user->id} ({$user->email}) has type 'parent' but no ParentProfile.");

            if (! $dryRun) {
                ParentProfile::create([
                    'user_id' => $user->id,
                    'full_name' => $user->name,
                    'email_snapshot' => $user->email,
                    'status' => 'active',
                ]);
                $this->info("Created ParentProfile for User ID {$user->id}");
            }
        }
    }
}
