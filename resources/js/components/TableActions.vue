<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Edit2 } from 'lucide-vue-next';

interface TableActionsProps {
    showEdit?: boolean;
    editTooltip?: string;
}

withDefaults(defineProps<TableActionsProps>(), {
    showEdit: true,
    editTooltip: 'Edit',
});

const emit = defineEmits<{
    edit: [];
}>();
</script>

<template>
    <div class="flex items-center gap-2">
        <TooltipProvider
            v-if="showEdit"
            :delay-duration="0"
            ignore-non-keyboard-focus
            disable-hoverable-content
        >
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="emit('edit')"
                        class="cursor-pointer"
                    >
                        <Edit2 class="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{{ editTooltip }}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>

        <!-- Slot for additional actions -->
        <slot />
    </div>
</template>