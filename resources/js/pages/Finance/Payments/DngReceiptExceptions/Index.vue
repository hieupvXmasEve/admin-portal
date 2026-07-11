<script setup lang="ts">
import JsonPayloadCard from '@/components/finance/JsonPayloadCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/composables/usePermission';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    items: {
        id: number;
        exception_type: string;
        provider_payment_id: string | null;
        mismatch_reasons: string[];
        affected_scope: Record<string, unknown>;
        raw_provider_evidence: Record<string, unknown>;
        request_id: number | null;
        student_name: string | null;
        outcome: string;
        next_action: string;
    }[];
}>();

const permission = usePermission();
const resolvingExceptionId = ref<number | null>(null);

const resolveException = (exceptionId: number) => {
    resolvingExceptionId.value = exceptionId;
    router.post(route('finance.dng.webhook-events.receipt-exceptions.resolve', exceptionId), {}, {
        preserveScroll: true,
        onFinish: () => {
            resolvingExceptionId.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Head title="DNG · Cần kiểm tra" />
        <div>
            <h1 class="text-2xl font-semibold">DNG · Cần kiểm tra</h1>
            <p class="text-muted-foreground text-sm">Provider evidence, kết quả hủy hoặc payment muộn cần được đối soát trước khi thu lại.</p>
        </div>
        <Card v-if="items.length === 0">
            <CardContent class="py-8 text-sm text-muted-foreground">Không có DNG exception cần kiểm tra.</CardContent>
        </Card>
        <Card v-for="exception in items" :key="exception.id" class="border-amber-300">
            <CardHeader class="flex-row items-center justify-between">
                <CardTitle class="flex items-center gap-2 text-amber-800"><AlertTriangle class="h-5 w-5" />Cần kiểm tra <Badge variant="outline">{{ exception.exception_type }}</Badge></CardTitle>
                <Button v-if="permission.can('resolve_finance_dng_receipt_exceptions')" variant="outline" size="sm" :disabled="resolvingExceptionId === exception.id" @click="resolveException(exception.id)">
                    {{ resolvingExceptionId === exception.id ? 'Đang xử lý...' : 'Đánh dấu đã đối soát' }}
                </Button>
            </CardHeader>
            <CardContent class="space-y-3 text-sm">
                <div>Provider payment: <span class="font-mono">{{ exception.provider_payment_id || '-' }}</span></div>
                <div>Kết quả: <span class="font-medium">{{ exception.outcome }}</span></div>
                <div>Hành động tiếp theo: {{ exception.next_action }}</div>
                <div>Lý do: {{ exception.mismatch_reasons.join('; ') }}</div>
                <Link v-if="exception.request_id" :href="route('finance.dng.payment-requests.show', exception.request_id)" class="text-blue-600 hover:underline">DNG request #{{ exception.request_id }} · {{ exception.student_name || 'Unknown student' }}</Link>
                <JsonPayloadCard title="Affected scope" :payload="exception.affected_scope" />
                <JsonPayloadCard title="Raw provider evidence" :payload="exception.raw_provider_evidence" />
            </CardContent>
        </Card>
    </div>
</template>
