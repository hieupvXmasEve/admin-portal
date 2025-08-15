<script setup lang="ts">
import { useSystemConfig } from '@/composables/useSystemConfig';
const { getLogoText } = useSystemConfig();
</script>

<template>
    <img :src="getLogoText" alt="Logo">
</template>
