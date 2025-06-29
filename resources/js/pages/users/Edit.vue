<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
// Badge component inline since it doesn't exist in UI components
import type { User } from '@/types/User';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ChevronRight, Shield, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Permission {
    id: number;
    name: string;
    code: string;
    description: string;
    parent_id: number | null;
    children?: Permission[];
}

interface Role {
    id: number;
    name: string;
    permissions: Permission[];
}

const props = defineProps<{
    user: User;
    roles: Role[];
    userRoleIds: number[];
}>();

// Form data - initialize with user's current roles
const form = useForm({
    selectedRoles: [...props.userRoleIds] as number[],
});

// UI state
const expandedRoles = ref<Set<number>>(new Set());

// Toggle role expansion
const toggleRoleExpansion = (roleId: number) => {
    if (expandedRoles.value.has(roleId)) {
        expandedRoles.value.delete(roleId);
    } else {
        expandedRoles.value.add(roleId);
    }
};

// Check if role is expanded
const isRoleExpanded = (roleId: number) => {
    return expandedRoles.value.has(roleId);
};

// Handle role selection
const toggleRoleSelection = (roleId: number) => {
    const currentRoles = [...form.selectedRoles];
    const index = currentRoles.indexOf(roleId);
    if (index > -1) {
        currentRoles.splice(index, 1);
    } else {
        currentRoles.push(roleId);
    }
    form.selectedRoles = currentRoles;
};

// Check if role is selected
const isRoleSelected = (roleId: number) => {
    return form.selectedRoles.includes(roleId);
};

// Get selected roles with their permissions
const selectedRolesWithPermissions = computed(() => {
    return props.roles.filter((role) => form.selectedRoles.includes(role.id));
});

// Get all unique permissions from selected roles
const allSelectedPermissions = computed(() => {
    const permissions = new Map();

    selectedRolesWithPermissions.value.forEach((role) => {
        role.permissions.forEach((permission) => {
            permissions.set(permission.id, permission);
            // Add children permissions
            if (permission.children) {
                permission.children.forEach((child) => {
                    permissions.set(child.id, child);
                });
            }
        });
    });

    return Array.from(permissions.values());
});

// Submit form
const handleSubmit = () => {
    console.log('Form data being sent:', form.data());
    form.put(`/users/${props.user.id}`, {
        onSuccess: () => {
            router.visit('/users');
        },
    });
};

// Go back to users list
const goBack = () => {
    router.visit('/users');
};
</script>

<template>
    <Head title="Edit User" />
    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h1 class="text-2xl font-semibold">Edit User</h1>
            <p class="text-muted-foreground">Update user roles and permissions</p>
        </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- User Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        User Information
                    </CardTitle>
                    <CardDescription> User details (read-only)</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Full Name</Label>
                        <Input disabled id="name" :model-value="user.name" />
                        <p class="text-muted-foreground text-xs">Name cannot be changed from this page</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Email Address</Label>
                        <Input disabled id="email" :model-value="user.email" type="email" readonly />
                        <p class="text-muted-foreground text-xs">Email cannot be changed from this page</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Selected Roles Summary -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        Selected Roles Summary
                    </CardTitle>
                    <CardDescription> Overview of selected roles and their permissions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="selectedRolesWithPermissions.length === 0" class="text-muted-foreground py-8 text-center">
                        <Shield class="mx-auto mb-2 h-12 w-12 opacity-50" />
                        <p>No roles selected</p>
                        <p class="text-sm">Select roles from the table below</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div>
                            <h4 class="mb-2 font-medium">Selected Roles ({{ selectedRolesWithPermissions.length }})</h4>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="role in selectedRolesWithPermissions"
                                    :key="role.id"
                                    class="bg-secondary text-secondary-foreground inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ role.name }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <h4 class="mb-2 font-medium">Total Permissions ({{ allSelectedPermissions.length }})</h4>
                            <div class="max-h-32 overflow-y-auto">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="permission in allSelectedPermissions"
                                        :key="permission.id"
                                        class="bg-muted text-muted-foreground inline-flex items-center rounded border px-2 py-1 text-xs font-medium"
                                    >
                                        {{ permission.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Roles Selection Table -->
        <Card>
            <CardHeader>
                <CardTitle>Role Assignment</CardTitle>
                <CardDescription> Update the roles assigned to this user. Click on a role to view its permissions. </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12">Select</TableHead>
                                <TableHead>Role Name</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead class="w-12">Details</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="role in roles" :key="role.id">
                                <!-- Role Row -->
                                <TableRow>
                                    <TableCell>
                                        <Checkbox :model-value="isRoleSelected(role.id)" @update:model-value="toggleRoleSelection(role.id)" />
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ role.name }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="permission in role.permissions.slice(0, 3)"
                                                :key="permission.id"
                                                class="bg-muted text-muted-foreground inline-flex items-center rounded border px-2 py-1 text-xs font-medium"
                                            >
                                                {{ permission.name }}
                                            </span>
                                            <span
                                                v-if="role.permissions.length > 3"
                                                class="bg-secondary text-secondary-foreground inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            >
                                                +{{ role.permissions.length - 3 }} more
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Button type="button" variant="ghost" size="icon" @click="toggleRoleExpansion(role.id)" class="h-8 w-8">
                                            <ChevronRight v-if="!isRoleExpanded(role.id)" class="h-4 w-4" />
                                            <ChevronDown v-else class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expanded Permissions Row -->
                                <TableRow v-if="isRoleExpanded(role.id)" class="bg-muted/50">
                                    <TableCell colspan="4" class="p-0">
                                        <div class="space-y-3 p-4">
                                            <h4 class="text-sm font-medium">Permissions for {{ role.name }}:</h4>

                                            <div v-if="role.permissions.length === 0" class="text-muted-foreground text-sm">
                                                No permissions assigned to this role.
                                            </div>

                                            <div v-else class="space-y-3">
                                                <!-- Parent permissions with children -->
                                                <div
                                                    v-for="permission in role.permissions.filter((p) => p.parent_id === null)"
                                                    :key="permission.id"
                                                    class="space-y-2"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="bg-primary text-primary-foreground inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                        >
                                                            {{ permission.name }}
                                                        </span>
                                                        <span v-if="permission.description" class="text-muted-foreground text-xs">
                                                            {{ permission.description }}
                                                        </span>
                                                    </div>

                                                    <!-- Child permissions -->
                                                    <div v-if="permission.children && permission.children.length > 0" class="ml-4 space-y-1">
                                                        <div v-for="child in permission.children" :key="child.id" class="flex items-center gap-2">
                                                            <span
                                                                class="bg-muted text-muted-foreground inline-flex items-center rounded border px-2 py-1 text-xs font-medium"
                                                            >
                                                                {{ child.name }}
                                                            </span>
                                                            <span v-if="child.description" class="text-muted-foreground text-xs">
                                                                {{ child.description }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Orphaned child permissions (those without parent in this role) -->
                                                <div
                                                    v-for="permission in role.permissions.filter(
                                                        (p) => p.parent_id !== null && !role.permissions.some((parent) => parent.id === p.parent_id),
                                                    )"
                                                    :key="permission.id"
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="bg-muted text-muted-foreground inline-flex items-center rounded border px-2 py-1 text-xs font-medium"
                                                    >
                                                        {{ permission.name }}
                                                    </span>
                                                    <span v-if="permission.description" class="text-muted-foreground text-xs">
                                                        {{ permission.description }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4">
            <Button type="button" variant="outline" @click="goBack"> Cancel</Button>
            <Button type="submit" :disabled="form.processing" class="min-w-[120px]">
                <span v-if="form.processing">Updating...</span>
                <span v-else>Update User</span>
            </Button>
        </div>
    </form>
</template>
