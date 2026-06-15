<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Student360Actions } from '@/types/finance';
import { ChevronDown } from 'lucide-vue-next';

defineProps<{
    actions: Student360Actions;
    hasUnapplied: boolean;
}>();

const emit = defineEmits<{
    (e: 'recordPayment'): void;
    (e: 'allocate'): void;
}>();
</script>

<template>
    <DropdownMenu v-if="actions.can_record_payment || (actions.can_allocate && hasUnapplied)">
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
                Thao tác
                <ChevronDown class="ml-1 size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem v-if="actions.can_record_payment" @click="emit('recordPayment')">
                Ghi nhận thanh toán
            </DropdownMenuItem>
            <DropdownMenuItem v-if="actions.can_allocate && hasUnapplied" @click="emit('allocate')">
                Phân bổ
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>