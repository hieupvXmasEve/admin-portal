<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Role } from '@/types/forms';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface VisibilityRole {
    role_id: number;
    visibility_level: string;
    min_aggregation_threshold?: number;
}

interface Props {
    visibilityRoles: number[];
    resultVisibility: VisibilityRole[];
    roles: Role[];
    visibilityLevels: Record<string, string>;
}

interface Emits {
    (e: 'update:visibilityRoles', roles: number[]): void;
    (e: 'update:resultVisibility', visibility: VisibilityRole[]): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Computed
const localVisibilityRoles = computed({
    get: () => props.visibilityRoles,
    set: (value) => emit('update:visibilityRoles', value),
});

const localResultVisibility = computed({
    get: () => props.resultVisibility,
    set: (value) => emit('update:resultVisibility', value),
});

// Methods
const toggleRoleVisibility = (roleId: number) => {
    const roles = [...localVisibilityRoles.value];
    const index = roles.indexOf(roleId);

    if (index > -1) {
        roles.splice(index, 1);
    } else {
        roles.push(roleId);
    }

    localVisibilityRoles.value = roles;
};

const addResultVisibility = () => {
    const newVisibility: VisibilityRole = {
        role_id: 0,
        visibility_level: 'own_submission',
    };
    localResultVisibility.value.push(newVisibility);
};

const removeResultVisibility = (index: number) => {
    localResultVisibility.value.splice(index, 1);
};

const getRoleName = (roleId: number) => {
    return props.roles.find((role) => role.id === roleId)?.name || 'Unknown Role';
};

const needsThreshold = (visibilityLevel: string) => {
    return visibilityLevel === 'aggregated';
};
const getVisibilityDescription = (level: string) => {
    switch (level) {
        case 'own_submission':
            return 'Users can only view their own submissions';
        case 'aggregated':
            return 'Users can view aggregated statistics when minimum threshold is met';
        case 'full_detail':
            return 'Users can view all individual responses and details';
        default:
            return 'Unknown visibility level';
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Form Visibility -->
        <Card>
            <CardHeader>
                <CardTitle>Form Visibility</CardTitle>
                <CardDescription> Control who can see and submit this form </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label class="text-base font-medium">Who can submit this form?</Label>
                    <p class="text-muted-foreground mb-4 text-sm">Select the roles that should be able to see and submit this form</p>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div v-for="role in roles" :key="role.id" class="flex items-center space-x-2">
                            <Checkbox :id="`visibility_role_${role.id}`" :model-value="localVisibilityRoles.includes(role.id)" @update:model-value="toggleRoleVisibility(role.id)" />
                            <Label :for="`visibility_role_${role.id}`" class="text-sm font-normal">
                                {{ role.name }}
                            </Label>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Result Visibility -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center justify-between">
                    Result Visibility
                    <Button size="sm" @click="addResultVisibility">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Rule
                    </Button>
                </CardTitle>
                <CardDescription> Configure who can view form responses and at what level of detail </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-for="(visibility, index) in localResultVisibility" :key="index" class="space-y-4 rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium">Visibility Rule {{ index + 1 }}</h4>
                        <Button variant="ghost" size="sm" @click="removeResultVisibility(index)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Role</Label>
                            <Select v-model:model-value="visibility.role_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="0" disabled>Select a role</SelectItem>
                                    <SelectItem v-for="role in roles" :key="role.id" :value="role.id">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>Visibility Level</Label>
                            <Select v-model:model-value="visibility.visibility_level">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select visibility level" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in visibilityLevels" :key="value" :value="value">
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Aggregation Threshold -->
                    <div v-if="needsThreshold(visibility.visibility_level)" class="space-y-2">
                        <Label>Minimum Aggregation Threshold</Label>
                        <Input v-model.number="visibility.min_aggregation_threshold" type="number" placeholder="e.g., 5" min="1" />
                        <p class="text-muted-foreground text-sm">Minimum number of responses required before aggregated data is shown</p>
                    </div>

                    <!-- Visibility Level Description -->
                    <div class="bg-muted/50 rounded-lg p-3">
                        <p class="text-sm">
                            <strong>{{ visibilityLevels[visibility.visibility_level] || 'Unknown' }}:</strong>
                            {{ getVisibilityDescription(visibility.visibility_level) }}
                        </p>
                    </div>
                </div>

                <div v-if="localResultVisibility.length === 0" class="text-muted-foreground py-8 text-center">
                    <p>No visibility rules configured</p>
                    <p class="text-sm">Add rules to control who can view form responses</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
