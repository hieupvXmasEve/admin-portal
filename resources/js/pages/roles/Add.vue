<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Settings, Shield } from 'lucide-vue-next';
import { computed } from 'vue';

interface Permission {
    id: number;
    name: string;
    code: string;
    description: string;
    parent_id: number | null;
    children?: Permission[];
}

defineProps<{
    permissions: Permission[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Roles',
        href: '/roles',
    },
    {
        title: 'Add Role',
        href: '/roles/add',
    },
];

// Form data
const form = useForm({
    name: '',
    selectedPermissions: [] as number[],
});

// Handle permission selection
const togglePermissionSelection = (permissionId: number) => {
    const currentPermissions = [...form.selectedPermissions];
    const index = currentPermissions.indexOf(permissionId);
    if (index > -1) {
        currentPermissions.splice(index, 1);
    } else {
        currentPermissions.push(permissionId);
    }
    form.selectedPermissions = currentPermissions;
};

// Handle parent permission selection (group checkbox)
const toggleParentPermissionSelection = (parentPermission: Permission) => {
    const childIds = parentPermission.children?.map((child) => child.id) || [];
    const allChildrenSelected = childIds.every((id) => form.selectedPermissions.includes(id));

    if (allChildrenSelected) {
        // Unselect all children
        const currentPermissions = form.selectedPermissions.filter((id) => !childIds.includes(id));
        form.selectedPermissions = currentPermissions;
    } else {
        // Select all children
        const currentPermissions = [...form.selectedPermissions];
        childIds.forEach((id) => {
            if (!currentPermissions.includes(id)) {
                currentPermissions.push(id);
            }
        });
        form.selectedPermissions = currentPermissions;
    }
};

// Check if permission is selected
const isPermissionSelected = (permissionId: number) => {
    return form.selectedPermissions.includes(permissionId);
};

// Check if parent permission (group) is selected
const isParentPermissionSelected = (parentPermission: Permission) => {
    const childIds = parentPermission.children?.map((child) => child.id) || [];
    return childIds.length > 0 && childIds.every((id) => form.selectedPermissions.includes(id));
};

// Check if parent permission is partially selected
const isParentPermissionPartiallySelected = (parentPermission: Permission) => {
    const childIds = parentPermission.children?.map((child) => child.id) || [];
    const selectedChildIds = childIds.filter((id) => form.selectedPermissions.includes(id));
    return selectedChildIds.length > 0 && selectedChildIds.length < childIds.length;
};

// Get selected permissions count
const selectedPermissionsCount = computed(() => {
    return form.selectedPermissions.length;
});

// Submit form
const handleSubmit = () => {
    console.log('Form data being sent:', form.data());
    form.post('/roles', {
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
    <Head title="Add Role" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-2xl font-semibold">Add New Role</h1>
                    <p class="text-muted-foreground">Create a new role and assign permissions</p>
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
                            <CardDescription> Enter the basic information for the new role </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name">Role Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="Enter role name"
                                    required
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
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
                            <div v-if="selectedPermissionsCount === 0" class="text-muted-foreground py-8 text-center">
                                <Shield class="mx-auto mb-2 h-12 w-12 opacity-50" />
                                <p>No permissions selected</p>
                                <p class="text-sm">Select permissions from the groups below</p>
                            </div>

                            <div v-else class="space-y-4">
                                <div>
                                    <h4 class="mb-2 font-medium">Total Permissions ({{ selectedPermissionsCount }})</h4>
                                    <div class="max-h-32 overflow-y-auto">
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="permissionId in form.selectedPermissions"
                                                :key="permissionId"
                                                class="bg-muted text-muted-foreground inline-flex items-center rounded border px-2 py-1 text-xs font-medium"
                                            >
                                                {{ permissions.flatMap((p) => p.children || []).find((child) => child.id === permissionId)?.name }}
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
                        <CardDescription> Select permissions for this role. Permissions are grouped by category. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <div v-for="parentPermission in permissions" :key="parentPermission.id" class="space-y-3">
                                <!-- Parent Permission Group Header -->
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg p-3">
                                    <Checkbox
                                        :model-value="isParentPermissionSelected(parentPermission)"
                                        :indeterminate="isParentPermissionPartiallySelected(parentPermission)"
                                        @update:model-value="toggleParentPermissionSelection(parentPermission)"
                                    />
                                    <div class="flex-1">
                                        <h3 class="text-sm font-medium">{{ parentPermission.name }}</h3>
                                        <p v-if="parentPermission.description" class="text-muted-foreground text-xs">
                                            {{ parentPermission.description }}
                                        </p>
                                    </div>
                                    <span class="text-muted-foreground text-xs"> {{ parentPermission.children?.length || 0 }} permissions </span>
                                </div>

                                <!-- Child Permissions -->
                                <div v-if="parentPermission.children && parentPermission.children.length > 0" class="ml-6 space-y-2">
                                    <div
                                        v-for="childPermission in parentPermission.children"
                                        :key="childPermission.id"
                                        class="hover:bg-muted/30 flex items-center gap-3 rounded p-2"
                                    >
                                        <Checkbox
                                            :model-value="isPermissionSelected(childPermission.id)"
                                            @update:model-value="togglePermissionSelection(childPermission.id)"
                                        />
                                        <div class="flex-1">
                                            <span class="text-sm font-medium">{{ childPermission.name }}</span>
                                            <p v-if="childPermission.description" class="text-muted-foreground text-xs">
                                                {{ childPermission.description }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- No child permissions message -->
                                <div v-else class="text-muted-foreground ml-6 text-sm">No specific permissions in this category</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-4">
                    <Button type="button" variant="outline" @click="goBack"> Cancel </Button>
                    <Button type="submit" :disabled="form.processing || !form.name" class="min-w-[120px]">
                        <span v-if="form.processing">Creating...</span>
                        <span v-else>Create Role</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
