<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Auth\Access\Response;
use App\Helpers\PermissionHelper;

class WalletTransactionPolicy
{
    /**
     * Determine whether the user can view any wallet transactions.
     */
    public function viewAny(User $user): bool
    {
        return PermissionHelper::hasPermission($user, 'view_any_wallet_transaction');
    }

    /**
     * Determine whether the user can view a specific transaction.
     */
    public function view(User $user, WalletTransaction $walletTransaction): bool
    {
        return PermissionHelper::hasPermission($user, 'view_wallet_transaction') ||
               PermissionHelper::hasPermission($user, 'view_any_wallet_transaction');
    }

    /**
     * Transactions are created through the service layer, not manually.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Transactions should not be updated after creation.
     */
    public function update(User $user, WalletTransaction $walletTransaction): bool
    {
        return false;
    }

    /**
     * Transactions should not be deleted (audit trail).
     */
    public function delete(User $user, WalletTransaction $walletTransaction): bool
    {
        return false;
    }

    /**
     * Transactions should not be restored.
     */
    public function restore(User $user, WalletTransaction $walletTransaction): bool
    {
        return false;
    }

    /**
     * Transactions should not be force deleted (audit trail).
     */
    public function forceDelete(User $user, WalletTransaction $walletTransaction): bool
    {
        return false;
    }
}
