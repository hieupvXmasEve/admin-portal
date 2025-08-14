<script setup lang="ts">
import { useSystemConfig } from '@/composables/useSystemConfig';
const { getLogoText } = useSystemConfig();
</script>

<template>
    <div
        class="size-6"
        :style="{
            WebkitMask: `url(${getLogoText}) no-repeat center / contain`,
            mask: `url(${getLogoText}) no-repeat center / contain`,
            backgroundColor: '#3ab60c', // đổi màu ở đây
        }"
    ></div>
</template>
