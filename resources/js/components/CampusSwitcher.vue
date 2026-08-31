<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useSystemConfig } from '@/composables/useSystemConfig';
import { CAMPUS_ROUTE_NAMES } from '@/constants';
import type { Campus, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<SharedData>();
const { systemConfig } = useSystemConfig();

const campuses = computed<Campus[]>(() => page.props.auth.campuses ?? []);
const currentCampus = computed<Campus | null>(() => page.props.auth.current_campus ?? null);
// More than one campus turns the header into a switcher (sidebar-07 team-switcher pattern).
const isSwitchable = computed(() => campuses.value.length > 1);

const switchCampus = (campus: Campus) => {
    if (campus.id === page.props.auth.current_campus_id) return;

    router.post(route(CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_SET_CURRENT), { selectedCampus: campus.id }, { preserveScroll: true });
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <!-- Single campus (or none): keep the plain link behaviour -->
            <SidebarMenuButton v-if="!isSwitchable" size="lg" as-child>
                <Link :href="route('dashboard')">
                    <div class="bg-background text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                        <AppLogo />
                    </div>
                    <div class="grid flex-1 text-left text-sm leading-tight">
                        <span class="truncate font-semibold">{{ systemConfig.app_name }}</span>
                        <span class="truncate text-xs">Campus: {{ currentCampus?.name }}</span>
                    </div>
                </Link>
            </SidebarMenuButton>

            <DropdownMenu v-else>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                        <div class="bg-background text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                            <AppLogo />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">{{ systemConfig.app_name }}</span>
                            <span class="truncate text-xs">Campus: {{ currentCampus?.name }}</span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-64" align="start" side="right">
                    <DropdownMenuLabel class="text-muted-foreground text-xs"> Chọn cơ sở làm việc </DropdownMenuLabel>
                    <DropdownMenuItem v-for="campus in campuses" :key="campus.id" class="gap-2" :disabled="campus.id === page.props.auth.current_campus_id" @click="switchCampus(campus)">
                        <div class="bg-background text-sidebar-primary-foreground flex size-6 items-center justify-center rounded-md border">
                            {{ campus.code || campus.name.charAt(0) }}
                        </div>
                        <span class="flex-1 truncate">{{ campus.name }}</span>
                        <Check v-if="campus.id === page.props.auth.current_campus_id" class="ml-auto size-4" />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="route('dashboard')" class="text-muted-foreground w-full text-xs"> Về trang tổng quan </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
