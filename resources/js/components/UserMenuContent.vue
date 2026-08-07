<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { SharedData, User } from '@/types';
import { CAMPUS_ROUTE_NAMES } from '@/constants';
import { Link, router, usePage } from '@inertiajs/vue3';
import { HelpCircle, LogOut, MapPin, Settings } from 'lucide-vue-next';

interface Props {
    user: User;
}

const page = usePage<SharedData>();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" method="post" :href="route(CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_CHANGE)" as="button">
                <MapPin class="mr-2 h-4 w-4" />
                Change Campus
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="page.props.user_guide_url" :as-child="true">
            <a class="flex w-full items-center" :href="page.props.user_guide_url" target="_blank" rel="noopener noreferrer">
                <HelpCircle class="mr-2 h-4 w-4" />
                Hướng dẫn
            </a>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" @click="handleLogout" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
