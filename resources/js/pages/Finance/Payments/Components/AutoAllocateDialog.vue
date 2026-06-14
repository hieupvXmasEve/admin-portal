<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Loader2, Sparkles } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'close'): void;
}>();

const { showConfirmDialog } = useGlobalConfirmDialog();

const isLoading = ref(false);

const defaultPriorities = [
    { id: 'tuition_term', label: 'Tuition Term' },
    { id: 'egc_level_fee', label: 'EGC Level Fee' },
    { id: 'retake_fee', label: 'Retake Fee' },
    { id: 'manual_fee', label: 'Manual Adjustment' },
];

const priorities = ref([...defaultPriorities]);

const moveUp = (index: number) => {
    if (index === 0) return;
    const temp = priorities.value[index];
    priorities.value[index] = priorities.value[index - 1];
    priorities.value[index - 1] = temp;
};

const moveDown = (index: number) => {
    if (index === priorities.value.length - 1) return;
    const temp = priorities.value[index];
    priorities.value[index] = priorities.value[index + 1];
    priorities.value[index + 1] = temp;
};

const runAllocate = () => {
    isLoading.value = true;
    router.post(
        route('finance.payments.auto-allocate'),
        {
            priority_order: priorities.value.map((p) => p.id),
        },
        {
            onSuccess: () => {
                toast.success('Auto-allocation completed successfully');
                emit('update:open', false);
            },
            onError: () => {
                toast.error('Failed to auto-allocate payments');
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

const handleAllocate = () => {
    showConfirmDialog(
        {
            title: 'Xác nhận tự động phân bổ',
            message: 'Tự động phân bổ toàn bộ tiền chưa phân bổ vào các khoản phí còn nợ theo thứ tự ưu tiên đã chọn? Thao tác ghi vào sổ thanh toán.',
            confirmText: 'Bắt đầu phân bổ',
            cancelText: 'Huỷ bỏ',
        },
        {
            onConfirm: () => runAllocate(),
        },
    );
};

watch(
    () => props.open,
    (val) => {
        if (val) {
            // Reset to default or keep last used? Resetting for now to ensure consistency with "Default"
            priorities.value = [...defaultPriorities];
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Auto Allocate Payments</DialogTitle>
                <DialogDescription> Automatically apply unallocated payments to outstanding charges based on the priority order below. </DialogDescription>
            </DialogHeader>

            <div class="py-4">
                <Label class="mb-4 block">Priority Order (High to Low)</Label>
                <div class="space-y-2">
                    <div v-for="(item, index) in priorities" :key="item.id" class="flex items-center justify-between rounded-md border bg-white p-3">
                        <span class="text-sm font-medium">{{ index + 1 }}. {{ item.label }}</span>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="index === 0" @click="moveUp(index)">
                                <ArrowUp class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="index === priorities.length - 1" @click="moveDown(index)">
                                <ArrowDown class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="$emit('update:open', false)"> Cancel </Button>
                <Button @click="handleAllocate" :disabled="isLoading">
                    <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                    <Sparkles v-else class="mr-2 h-4 w-4" />
                    Start Allocation
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
