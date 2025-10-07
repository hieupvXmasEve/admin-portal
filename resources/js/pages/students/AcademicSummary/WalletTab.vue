<script setup lang="ts">
import TransactionHistory from '@/components/TransactionHistory.vue';
import { Card, CardContent } from '@/components/ui/card';
import type { PaginatedTransactions, StudentCashWallet, WalletStats } from '@/types/wallet';
import { router } from '@inertiajs/vue3';
import { FileText, RotateCcw, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
// import AdjustmentModal from './Wallets/AdjustmentModal.vue';
// import DepositModal from './Wallets/DepositModal.vue';

interface Props {
    wallet: StudentCashWallet;
    transactions: PaginatedTransactions;
    stats: WalletStats;
    studentId: number;
    loading?: boolean;
}

defineProps<Props>();

// const showDepositModal = ref(false);
// const showAdjustmentModal = ref(false);

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' VND';
};

// const handleDepositSuccess = () => {
//     showDepositModal.value = false;
//     router.reload({ only: ['wallet', 'transactions', 'stats'] });
// };
//
// const handleAdjustmentSuccess = () => {
//     showAdjustmentModal.value = false;
//     router.reload({ only: ['wallet', 'transactions', 'stats'] });
// };

const handleTransactionNavigate = (url: string) => {
    router.visit(url, {
        only: ['transactions'],
        preserveState: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Action Buttons -->
        <!--        <div class="flex justify-end space-x-3">-->
        <!--            <Button v-if="can.deposit" @click="showDepositModal = true" class="flex items-center gap-2">-->
        <!--                <Plus class="h-4 w-4" />-->
        <!--                Deposit-->
        <!--            </Button>-->
        <!--            <Button v-if="can.adjust" @click="showAdjustmentModal = true" variant="secondary" class="flex items-center gap-2">-->
        <!--                <Settings class="h-4 w-4" />-->
        <!--                Adjust-->
        <!--            </Button>-->
        <!--        </div>-->

        <!-- Wallet Balance Card -->
        <Card>
            <CardContent class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <Wallet class="h-8 w-8 text-green-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="truncate text-sm font-medium text-gray-500">Current Balance</dt>
                            <dd class="text-3xl font-semibold text-gray-900">
                                {{ wallet.formatted_balance }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <TrendingUp class="h-6 w-6 text-green-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Total Deposits</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    {{ formatCurrency(stats.total_deposits) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <TrendingDown class="h-6 w-6 text-red-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Total Payments</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    {{ formatCurrency(stats.total_payments) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <RotateCcw class="h-6 w-6 text-blue-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Total Refunds</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    {{ formatCurrency(stats.total_refunds) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <FileText class="h-6 w-6 text-gray-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Transactions</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    {{ stats.transaction_count }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Transaction History -->
        <TransactionHistory :transactions="transactions" title="Transaction History" description="Recent wallet transactions and balance changes." :show-running-balance="true" @navigate="handleTransactionNavigate" />

        <!-- Deposit Modal -->
        <!--        <DepositModal v-model:show="showDepositModal" :wallet-id="wallet.id" @success="handleDepositSuccess" />-->

        <!-- Adjustment Modal -->
        <!--        <AdjustmentModal v-model:show="showAdjustmentModal" :wallet-id="wallet.id" @success="handleAdjustmentSuccess" />-->
    </div>
</template>
