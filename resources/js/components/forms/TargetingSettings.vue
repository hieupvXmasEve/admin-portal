<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Campus } from '@/types/forms';
import { Calendar, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Target {
    campus_id?: number;
    scope_type: string;
    scope_id?: number;
    start_at: string;
    end_at?: string;
    submission_limit_per_user: number;
}

interface Props {
    targets: Target[];
    campuses: Campus[];
    scopeTypes: Record<string, string>;
}

interface Emits {
    (e: 'update:targets', targets: Target[]): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Computed
const localTargets = computed({
    get: () => props.targets,
    set: (value) => emit('update:targets', value),
});

// Methods
const addTarget = () => {
    const newTarget: Target = {
        scope_type: 'global',
        start_at: new Date().toISOString().slice(0, 16), // Format for datetime-local input
        submission_limit_per_user: 1,
    };
    localTargets.value.push(newTarget);
};

const removeTarget = (index: number) => {
    localTargets.value.splice(index, 1);
};

const getCampusName = (campusId?: number) => {
    if (!campusId) return 'All Campuses';
    return props.campuses.find((campus) => campus.id === campusId)?.name || 'Unknown Campus';
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

const getScopeDescription = (scopeType: string) => {
    switch (scopeType) {
        case 'global':
            return 'Available to all users across all contexts';
        case 'course':
            return 'Available to users in a specific course';
        case 'section':
            return 'Available to users in a specific section';
        case 'class_session':
            return 'Available to users in a specific class session';
        default:
            return 'Unknown scope type';
    }
};

const needsScopeId = (scopeType: string) => {
    return ['course', 'section', 'class_session'].includes(scopeType);
};
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center justify-between">
                    Form Targets
                    <Button size="sm" @click="addTarget">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Target
                    </Button>
                </CardTitle>
                <CardDescription> Configure where and when this form should be available </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-for="(target, index) in localTargets" :key="index" class="space-y-4 rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium">Target {{ index + 1 }}</h4>
                        <Button variant="ghost" size="sm" @click="removeTarget(index)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Campus Selection -->
                        <div class="space-y-2">
                            <Label>Campus</Label>
                            <Select v-model:model-value="target.campus_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select campus" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Campuses</SelectItem>
                                    <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id">
                                        {{ campus.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Scope Type -->
                        <div class="space-y-2">
                            <Label>Scope Type</Label>
                            <Select v-model:model-value="target.scope_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select scope" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in scopeTypes" :key="value" :value="value">
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Scope ID (if needed) -->
                    <div v-if="needsScopeId(target.scope_type)" class="space-y-2">
                        <Label>Scope ID</Label>
                        <Input v-model.number="target.scope_id" type="number" placeholder="Enter the specific ID for this scope" />
                        <p class="text-muted-foreground text-sm">Enter the ID of the specific {{ target.scope_type }} where this form should be available</p>
                    </div>

                    <!-- Time Range -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Start Date & Time</Label>
                            <div class="relative">
                                <Calendar class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                <Input v-model="target.start_at" type="datetime-local" class="pl-8" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>End Date & Time (Optional)</Label>
                            <div class="relative">
                                <Calendar class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                <Input v-model="target.end_at" type="datetime-local" class="pl-8" />
                            </div>
                        </div>
                    </div>

                    <!-- Submission Limit -->
                    <div class="space-y-2">
                        <Label>Submission Limit per User</Label>
                        <Input v-model.number="target.submission_limit_per_user" type="number" min="1" placeholder="e.g., 1" />
                        <p class="text-muted-foreground text-sm">Maximum number of times each user can submit this form</p>
                    </div>

                    <!-- Target Summary -->
                    <div class="bg-muted/50 rounded-lg p-3">
                        <h5 class="mb-2 font-medium">Target Summary</h5>
                        <div class="space-y-1 text-sm">
                            <p><strong>Campus:</strong> {{ getCampusName(target.campus_id) }}</p>
                            <p><strong>Scope:</strong> {{ scopeTypes[target.scope_type] || target.scope_type }}</p>
                            <p v-if="target.scope_id"><strong>Scope ID:</strong> {{ target.scope_id }}</p>
                            <p><strong>Available from:</strong> {{ formatDateTime(target.start_at) }}</p>
                            <p v-if="target.end_at"><strong>Available until:</strong> {{ formatDateTime(target.end_at) }}</p>
                            <p><strong>Submission limit:</strong> {{ target.submission_limit_per_user }} per user</p>
                        </div>
                        <div class="mt-2 border-t pt-2">
                            <p class="text-muted-foreground text-sm">
                                {{ getScopeDescription(target.scope_type) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="localTargets.length === 0" class="text-muted-foreground py-8 text-center">
                    <p>No targets configured</p>
                    <p class="text-sm">Add targets to specify where and when this form should be available</p>
                </div>
            </CardContent>
        </Card>

        <!-- Targeting Help -->
        <Card>
            <CardHeader>
                <CardTitle>Targeting Guide</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div>
                        <h5 class="mb-2 font-medium">Scope Types</h5>
                        <div class="space-y-2 text-sm">
                            <div><strong>Global:</strong> Form is available to all users system-wide</div>
                            <div><strong>Course:</strong> Form is available to users enrolled in a specific course</div>
                            <div><strong>Section:</strong> Form is available to users in a specific course section</div>
                            <div><strong>Class Session:</strong> Form is available during/after a specific class session</div>
                        </div>
                    </div>

                    <div>
                        <h5 class="mb-2 font-medium">Best Practices</h5>
                        <ul class="text-muted-foreground space-y-1 text-sm">
                            <li>• Use Global scope for campus-wide feedback forms</li>
                            <li>• Use Course scope for course evaluation surveys</li>
                            <li>• Use Section scope for section-specific feedback</li>
                            <li>• Use Class Session scope for post-class surveys</li>
                            <li>• Set reasonable submission limits to prevent spam</li>
                            <li>• Always set end dates for time-sensitive forms</li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
