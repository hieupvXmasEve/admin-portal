<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Role } from '@/types/Role';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Settings, Shield } from 'lucide-vue-next';
import { computed } from 'vue';

interface Permission {
    id: number;
    name: string;
    code: string;
    description: string;
    display_name?: string;
    module: string;
}

interface PermissionGroup {
    module: string;
    display_name: string;
    permissions: Permission[];
}

const props = defineProps<{
    role: Role;
    permissions: Record<string, Permission[]>; // Object grouped by module
    rolePermissionIds: number[];
}>();

// Transform permissions object into array format expected by the component
const permissionGroups = computed(() => {
    const groups: PermissionGroup[] = [];

    for (const [module, permissions] of Object.entries(props.permissions)) {
        groups.push({
            module,
            display_name: module.charAt(0).toUpperCase() + module.slice(1).replace(/_/g, ' '),
            permissions,
        });
    }

    return groups;
});

// Form data - initialize with role's current permissions
const form = useForm({
    name: props.role.name,
    permissions: [...props.rolePermissionIds] as number[],
});

// Handle permission selection
const togglePermissionSelection = (permissionId: number) => {
    const currentPermissions = [...form.permissions];
    const index = currentPermissions.indexOf(permissionId);
    if (index > -1) {
        currentPermissions.splice(index, 1);
    } else {
        currentPermissions.push(permissionId);
    }
    form.permissions = currentPermissions;
};

// Handle module permission selection (group checkbox)
const toggleModulePermissionSelection = (permissionGroup: PermissionGroup) => {
    const modulePermissionIds = permissionGroup.permissions.map((permission) => permission.id);
    const allModulePermissionsSelected = modulePermissionIds.every((id) => form.permissions.includes(id));

    if (allModulePermissionsSelected) {
        // Unselect all permissions in this module
        const currentPermissions = form.permissions.filter((id) => !modulePermissionIds.includes(id));
        form.permissions = currentPermissions;
    } else {
        // Select all permissions in this module
        const currentPermissions = [...form.permissions];
        modulePermissionIds.forEach((id) => {
            if (!currentPermissions.includes(id)) {
                currentPermissions.push(id);
            }
        });
        form.permissions = currentPermissions;
    }
};

// Check if permission is selected
const isPermissionSelected = (permissionId: number) => {
    return form.permissions.includes(permissionId);
};

// Check if module (group) permissions are all selected
const isModulePermissionSelected = (permissionGroup: PermissionGroup) => {
    const modulePermissionIds = permissionGroup.permissions.map((permission) => permission.id);
    return modulePermissionIds.length > 0 && modulePermissionIds.every((id) => form.permissions.includes(id));
};

// Check if module permissions are partially selected
const isModulePermissionPartiallySelected = (permissionGroup: PermissionGroup) => {
    const modulePermissionIds = permissionGroup.permissions.map((permission) => permission.id);
    const selectedModulePermissionIds = modulePermissionIds.filter((id) => form.permissions.includes(id));
    return selectedModulePermissionIds.length > 0 && selectedModulePermissionIds.length < modulePermissionIds.length;
};

// Get selected permissions count
const permissionsCount = computed(() => {
    return form.permissions.length;
});

// Submit form
const handleSubmit = () => {
    form.put(`/roles/${props.role.id}`, {
        onSuccess: () => {
            router.visit('/roles');
        },
    });
};

// Go back to roles list
const goBack = () => {
    router.visit('/roles');
};
</script>

<template>
    <Head title="Edit Role" />
    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h1 class="text-2xl font-semibold">Edit Role</h1>
            <p class="text-muted-foreground">Update role permissions</p>
        </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Role Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Role Information
                    </CardTitle>
                    <CardDescription> Role details </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Role Name</Label>
                        <Input id="name" v-model="form.name" placeholder="Enter role name" required :class="{ 'border-red-500': form.errors.name }" />
                        <p v-if="form.errors.name" class="text-sm text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Selected Permissions Summary -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        Selected Permissions Summary
                    </CardTitle>
                    <CardDescription> Overview of selected permissions </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="permissionsCount === 0" class="text-muted-foreground py-8 text-center">
                        <Shield class="mx-auto mb-2 h-12 w-12 opacity-50" />
                        <p>No permissions selected</p>
                        <p class="text-sm">Select permissions from the groups below</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div>
                            <h4 class="mb-2 font-medium">Total Permissions ({{ permissionsCount }})</h4>
                            <div class="max-h-32 overflow-y-auto">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="permissionId in form.permissions"
                                        :key="permissionId"
                                        class="bg-muted text-muted-foreground inline-flex items-center rounded border px-2 py-1 text-xs font-medium"
                                    >
                                        {{
                                            permissionGroups
                                                .find((group) => group.permissions.some((permission) => permission.id === permissionId))
                                                ?.permissions.find((permission) => permission.id === permissionId)?.display_name
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Permissions Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Permission Assignment</CardTitle>
                <CardDescription> Update permissions for this role. Permissions are grouped by category. </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-6">
                    <div v-for="permissionGroup in permissionGroups" :key="permissionGroup.module" class="space-y-3">
                        <!-- Module Permission Group Header -->
                        <div class="bg-muted/50 flex items-center gap-3 rounded-lg p-3">
                            <Checkbox
                                :model-value="isModulePermissionSelected(permissionGroup)"
                                :indeterminate="isModulePermissionPartiallySelected(permissionGroup)"
                                @update:model-value="toggleModulePermissionSelection(permissionGroup)"
                            />
                            <div class="flex-1">
                                <h3 class="text-sm font-medium">{{ permissionGroup.display_name }}</h3>
                                <p class="text-muted-foreground text-xs">Permissions for {{ permissionGroup.module }} module</p>
                            </div>
                            <span class="text-muted-foreground text-xs"> {{ permissionGroup.permissions.length }} permissions </span>
                        </div>

                        <!-- Module Permissions -->
                        <div v-if="permissionGroup.permissions && permissionGroup.permissions.length > 0" class="ml-6 space-y-2">
                            <div
                                v-for="permission in permissionGroup.permissions"
                                :key="permission.id"
                                class="hover:bg-muted/30 flex items-center gap-3 rounded p-2"
                            >
                                <Checkbox
                                    :model-value="isPermissionSelected(permission.id)"
                                    @update:model-value="togglePermissionSelection(permission.id)"
                                />
                                <div class="flex-1">
                                    <span class="text-sm font-medium">{{ permission.display_name || permission.name }}</span>
                                    <p v-if="permission.description" class="text-muted-foreground text-xs">
                                        {{ permission.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- No permissions message -->
                        <div v-else class="text-muted-foreground ml-6 text-sm">No permissions in this module</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4">
            <Button type="button" variant="outline" @click="goBack"> Cancel </Button>
            <Button type="submit" :disabled="form.processing || !form.name" class="min-w-[120px]">
                <span v-if="form.processing">Updating...</span>
                <span v-else>Update Role</span>
            </Button>
        </div>
    </form>
</template>
