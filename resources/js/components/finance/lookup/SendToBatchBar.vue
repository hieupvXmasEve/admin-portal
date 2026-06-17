<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Bell, Send, X } from 'lucide-vue-next';

defineProps<{ count: number; canDng?: boolean; canRemind?: boolean }>();
const emit = defineEmits<{ (e: 'dng'): void; (e: 'reminders'): void; (e: 'clear'): void }>();
</script>

<template>
    <div v-if="count > 0" class="bg-background/95 sticky bottom-0 z-20 flex items-center justify-between gap-4 border-t px-4 py-3 backdrop-blur">
        <span class="text-sm font-medium">{{ count }} sinh viên đã chọn</span>
        <div class="flex gap-2">
            <Button v-if="canDng" size="sm" variant="outline" @click="emit('dng')">
                <Send class="mr-1 h-4 w-4" />
                Lập yêu cầu thanh toán DNG
            </Button>
            <Button v-if="canRemind" size="sm" variant="outline" @click="emit('reminders')">
                <Bell class="mr-1 h-4 w-4" />
                Nhắc nợ hàng loạt
            </Button>
            <Button size="sm" variant="ghost" @click="emit('clear')">
                <X class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
