<?php

namespace App\Policies;

use App\Models\StudentWallet;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Helpers\PermissionHelper;

class StudentWalletPolicy
{
    /**
     * Determine whether the user can view any student wallets.
     */
    public function viewAny(User $user): bool
    {
        return PermissionHelper::hasPermission($user, 'view_any_student_wallet');
    }

    /**
     * Determine whether the user can view a specific wallet.
     */
    public function view(User $user, StudentWallet $studentWallet): bool
    {
        return PermissionHelper::hasPermission($user, 'view_student_wallet') ||
               PermissionHelper::hasPermission($user, 'view_any_student_wallet');
    }

    /**
     * Wallets are automatically created, so no need for manual creation.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can adjust wallet balances.
     */
    public function update(User $user, StudentWallet $studentWallet): bool
    {
        return PermissionHelper::hasPermission($user, 'adjust_wallet_balance');
    }

    /**
     * Wallets should not be deleted.
     */
    public function delete(User $user, StudentWallet $studentWallet): bool
    {
        return false;
    }

    /**
     * Wallets should not be restored.
     */
    public function restore(User $user, StudentWallet $studentWallet): bool
    {
        return false;
    }

    /**
     * Wallets should not be force deleted.
     */
    public function forceDelete(User $user, StudentWallet $studentWallet): bool
    {
        return false;
    }
}
