<script setup lang="ts">
import { useSystemConfig } from '@/composables/useSystemConfig';
import { computed } from 'vue';

const { systemConfig } = useSystemConfig();
const monogram = computed(() => systemConfig.value.app_name.trim().slice(0, 1).toUpperCase());
</script>

<template>
    <img v-if="systemConfig.logo_text_url" :src="systemConfig.logo_text_url" :alt="`${systemConfig.app_name} logo`" class="size-full object-contain" />
    <span v-else class="text-xs font-semibold" :aria-label="`${systemConfig.app_name} logo is not configured`">
        {{ monogram }}
    </span>
</template>
