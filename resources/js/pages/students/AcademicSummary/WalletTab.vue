<script setup lang="ts">
import TransactionHistory from '@/components/TransactionHistory.vue';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaginatedTransactions, StudentCashWallet, TuitionPlan, WalletStats } from '@/types/wallet';
import { router } from '@inertiajs/vue3';
import { Calendar, FileText, GraduationCap, RotateCcw, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
// import AdjustmentModal from './Wallets/AdjustmentModal.vue';
// import DepositModal from './Wallets/DepositModal.vue';

interface Props {
    wallet: StudentCashWallet;
    transactions: PaginatedTransactions;
    stats: WalletStats;
    tuitionPlan?: TuitionPlan | null;
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

        <!-- Tuition Plan Card -->
        <Card v-if="tuitionPlan">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="text-primary h-5 w-5" />
                        Tuition Plan
                    </CardTitle>
                    <Badge v-if="tuitionPlan.is_active" variant="default">Active</Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Amount</p>
                        <p class="text-2xl font-bold text-gray-900">{{ new Intl.NumberFormat('vi-VN').format(tuitionPlan.total_amount) }} {{ tuitionPlan.currency }}</p>
                    </div>
                    <div v-if="tuitionPlan.curriculum_version">
                        <p class="text-sm font-medium text-gray-500">Curriculum Version</p>
                        <p class="text-lg font-semibold text-gray-900">{{ tuitionPlan.curriculum_version.version_code }}</p>
                        <p v-if="tuitionPlan.curriculum_version.program" class="text-sm text-gray-600">
                            {{ tuitionPlan.curriculum_version.program.name }}
                        </p>
                        <p v-if="tuitionPlan.curriculum_version.specialization" class="text-sm text-gray-600">
                            {{ tuitionPlan.curriculum_version.specialization.name }}
                        </p>
                    </div>
                </div>

                <div v-if="tuitionPlan.intake_semester" class="flex items-center gap-2 rounded-lg bg-gray-50 p-3">
                    <Calendar class="h-4 w-4 text-gray-600" />
                    <div>
                        <p class="text-sm font-medium text-gray-700">Intake Semester</p>
                        <p class="text-sm text-gray-600">{{ tuitionPlan.intake_semester.name }} ({{ tuitionPlan.intake_semester.code }})</p>
                    </div>
                </div>

                <div v-if="tuitionPlan.terms && tuitionPlan.terms.length > 0" class="space-y-2">
                    <Accordion type="single" collapsible>
                        <AccordionItem value="payment-terms">
                            <AccordionTrigger class="cursor-pointer text-sm font-medium text-gray-700"> Payment Terms ({{ tuitionPlan.terms.length }} term{{ tuitionPlan.terms.length > 1 ? 's' : '' }}) </AccordionTrigger>
                            <AccordionContent>
                                <div class="space-y-2 pt-2">
                                    <div v-for="term in tuitionPlan.terms" :key="term.id" class="flex items-center justify-between rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                                        <div>
                                            <p class="font-medium text-gray-900">Term {{ term.term_number }}</p>
                                            <p v-if="term.semester" class="text-sm text-gray-600">{{ term.semester.name }} ({{ term.semester.code }})</p>
                                            <p v-if="term.due_date" class="text-xs text-gray-500">Due: {{ term.formatted_due_date }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900">{{ new Intl.NumberFormat('vi-VN').format(term.amount) }} {{ tuitionPlan.currency }}</p>
                                        </div>
                                    </div>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </div>
            </CardContent>
        </Card>

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
