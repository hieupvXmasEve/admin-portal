<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import WalletAdjustmentModal from '@/components/Wallet/WalletAdjustmentModal.vue';
import { useApi } from '@/composables/useApiRequest';
import type { GoldTransaction, StudentWalletTabData, WalletAdjustmentRequest } from '@/types/wallet';
import { router } from '@inertiajs/vue3';
import { ArrowDownIcon, ArrowUpIcon, CoinsIcon, CreditCardIcon, HistoryIcon, PlusIcon, TrendingUpIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    wallet: StudentWalletTabData;
    studentId: number;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const showAdjustmentModal = ref(false);
const isAdjusting = ref(false);

// API instance
const api = useApi();

const formatGold = (amount: string | number): string => {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    return `${numAmount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} Gold`;
};

const getTransactionIcon = (transaction: GoldTransaction) => {
    switch (transaction.type) {
        case 'earn':
            return ArrowUpIcon;
        case 'spend':
            return ArrowDownIcon;
        case 'adjust':
            return CreditCardIcon;
        default:
            return CoinsIcon;
    }
};

const getTransactionClass = (transaction: GoldTransaction): string => {
    switch (transaction.type) {
        case 'earn':
            return 'text-green-600 bg-green-50';
        case 'spend':
            return 'text-red-600 bg-red-50';
        case 'adjust':
            return 'text-blue-600 bg-blue-50';
        default:
            return 'text-gray-600 bg-gray-50';
    }
};

const viewAllTransactions = () => {
    // Navigate to full transaction history page (could be implemented later)
    console.log('Navigate to full wallet transaction history');
};

const openAdjustmentModal = () => {
    showAdjustmentModal.value = true;
};

const handleAdjustmentSubmit = async (data: WalletAdjustmentRequest) => {
    try {
        isAdjusting.value = true;

        // Call the wallet adjustment API
        const result = await api.post(`/api/wallet/students/${props.studentId}/adjust`, {
            amount: data.amount,
            notes: data.notes,
        });

        // Handle the response based on the actual structure
        let apiResponse: any;

        // Try to access the response data in different ways
        if ((result.data as any)?.value) {
            apiResponse = (result.data as any).value;
        } else if (result.data) {
            apiResponse = result.data;
        } else if ((result as any).value) {
            apiResponse = (result as any).value;
        } else {
            apiResponse = result;
        }

        if (apiResponse?.success) {
            toast.success(apiResponse.message || 'Wallet balance adjusted successfully!');
            showAdjustmentModal.value = false;
            // Refresh the page to get updated data
            router.reload();
        } else {
            // Check if there's an actual error with meaningful content
            if ((result.error as any)?.value || (result.error as any)?._value) {
                throw result.error;
            } else {
                throw new Error(apiResponse?.message || 'Failed to adjust wallet balance');
            }
        }
    } catch (error: any) {
        console.error('Error adjusting balance:', error);

        // Handle different types of errors
        if (error.data?.errors) {
            // Validation errors
            const errorMessages = Object.values(error.data.errors).flat();
            toast.error(errorMessages.join(', '));
        } else if (error.data?.message) {
            // Business logic errors
            toast.error(error.data.message);
        } else {
            // Network or other errors
            toast.error('Failed to adjust wallet balance. Please try again.');
        }
    } finally {
        isAdjusting.value = false;
    }
};

const handleModalClose = () => {
    showAdjustmentModal.value = false;
};

// Computed properties for stats
const totalTransactions = computed(() => props.wallet?.stats?.transaction_count || 0);

// Safe accessors for wallet data
const walletBalance = computed(() => props.wallet?.summary?.wallet?.balance || '0.00');
const walletUpdatedAt = computed(() => props.wallet?.summary?.wallet?.formatted_updated_at || 'N/A');
const totalEarned = computed(() => props.wallet?.stats?.total_earned || 0);
const totalSpent = computed(() => props.wallet?.stats?.total_spent || 0);
const recentTransactions = computed(() => props.wallet?.recent_transactions || []);
</script>

<template>
    <div class="space-y-6">
        <!-- Wallet Overview Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Current Balance -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Current Balance</CardTitle>
                    <CoinsIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">
                        {{ formatGold(walletBalance) }}
                    </div>
                    <p class="text-muted-foreground text-xs">Last updated {{ walletUpdatedAt }}</p>
                </CardContent>
            </Card>

            <!-- Total Earned -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Earned</CardTitle>
                    <TrendingUpIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">
                        {{ formatGold(totalEarned) }}
                    </div>
                    <p class="text-muted-foreground text-xs">All-time earnings</p>
                </CardContent>
            </Card>

            <!-- Total Spent -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Spent</CardTitle>
                    <ArrowDownIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-red-600">
                        {{ formatGold(totalSpent) }}
                    </div>
                    <p class="text-muted-foreground text-xs">All-time spending</p>
                </CardContent>
            </Card>

            <!-- Total Transactions -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Transactions</CardTitle>
                    <HistoryIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ totalTransactions }}
                    </div>
                    <p class="text-muted-foreground text-xs">Total transactions</p>
                </CardContent>
            </Card>
        </div>

        <!-- Quick Actions -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CreditCardIcon class="h-5 w-5" />
                    Quick Actions
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex gap-2">
                    <Button @click="openAdjustmentModal" variant="outline" size="sm">
                        <PlusIcon class="mr-2 h-4 w-4" />
                        Adjust Balance
                    </Button>
                    <Button @click="viewAllTransactions" variant="outline" size="sm">
                        <HistoryIcon class="mr-2 h-4 w-4" />
                        View All Transactions
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Recent Transactions -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <HistoryIcon class="h-5 w-5" />
                    Recent Transactions
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-3">
                    <div v-for="i in 5" :key="i" class="animate-pulse">
                        <div class="flex items-center space-x-4">
                            <div class="h-10 w-10 rounded-full bg-gray-200"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-3/4 rounded bg-gray-200"></div>
                                <div class="h-3 w-1/2 rounded bg-gray-200"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="recentTransactions.length === 0" class="py-8 text-center">
                    <HistoryIcon class="mx-auto mb-3 h-12 w-12 text-gray-400" />
                    <p class="text-gray-500">No transactions yet</p>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="transaction in recentTransactions" :key="transaction.id" class="flex items-center space-x-4 rounded-lg border p-3">
                        <div :class="['rounded-full p-2', getTransactionClass(transaction)]">
                            <component :is="getTransactionIcon(transaction)" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ transaction.display_amount }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ transaction.notes || `${transaction.source_type_label} transaction` }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <Badge :variant="transaction.type === 'earn' ? 'default' : transaction.type === 'spend' ? 'destructive' : 'secondary'">
                                        {{ transaction.type_label }}
                                    </Badge>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ transaction.time_ago }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex justify-center">
                        <Button @click="viewAllTransactions" variant="outline" size="sm"> View All Transactions </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Wallet Adjustment Modal -->
        <WalletAdjustmentModal :is-open="showAdjustmentModal" :current-balance="walletBalance" :loading="isAdjusting" @close="handleModalClose" @submit="handleAdjustmentSubmit" />
    </div>
</template>
