<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    title: string;
    description?: string;
    payload: unknown;
    emptyLabel?: string;
}

withDefaults(defineProps<Props>(), {
    description: '',
    emptyLabel: 'No data captured.',
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">{{ description }}</CardDescription>
        </CardHeader>
        <CardContent>
            <pre v-if="payload" class="bg-muted max-h-[28rem] overflow-auto rounded-md border p-4 text-xs whitespace-pre-wrap">{{ JSON.stringify(payload, null, 2) }}</pre>
            <div v-else class="text-muted-foreground text-sm">{{ emptyLabel }}</div>
        </CardContent>
    </Card>
</template>
