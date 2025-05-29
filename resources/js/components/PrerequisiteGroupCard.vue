<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Select from '@/components/ui/select/Select.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import { Plus, X, Search, AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface PrerequisiteCondition {
    id?: number;
    type: 'prerequisite' | 'co_requisite' | 'concurrent_prerequisite' | 'anti_requisite' | 'assumed_knowledge' | 'credit_requirement';
    required_unit_id?: number;
    unit?: Unit;
    required_credits?: number;
    free_text?: string;
}

interface PrerequisiteGroup {
    id?: number;
    logic_operator: 'AND' | 'OR';
    description?: string;
    conditions: PrerequisiteCondition[];
}

const props = defineProps<{
    group: PrerequisiteGroup;
    groupIndex: number;
}>();

const emit = defineEmits<{
    'remove-group': [groupIndex: number];
    'update-operator': [groupIndex: number, operator: 'AND' | 'OR'];
    'update-description': [groupIndex: number, description: string];
    'add-condition': [groupIndex: number];
    'remove-condition': [groupIndex: number, conditionIndex: number];
    'update-condition-type': [groupIndex: number, conditionIndex: number, type: string];
    'update-condition-credits': [groupIndex: number, conditionIndex: number, credits: number];
    'update-condition-text': [groupIndex: number, conditionIndex: number, text: string];
    'open-unit-search': [groupIndex: number, conditionIndex: number];
}>();

// Computed properties for v-model binding
const groupDescription = computed({
    get: () => props.group.description || '',
    set: (value: string) => emit('update-description', props.groupIndex, value)
});

const groupOperator = computed({
    get: () => props.group.logic_operator,
    set: (value: 'AND' | 'OR') => emit('update-operator', props.groupIndex, value)
});

// Helper functions for condition v-model binding
const getConditionText = (conditionIndex: number) => {
    return computed({
        get: () => props.group.conditions[conditionIndex]?.free_text || '',
        set: (value: string) => emit('update-condition-text', props.groupIndex, conditionIndex, value)
    });
};

const getConditionCredits = (conditionIndex: number) => {
    return computed({
        get: () => props.group.conditions[conditionIndex]?.required_credits || 0,
        set: (value: number) => emit('update-condition-credits', props.groupIndex, conditionIndex, value)
    });
};

const getConditionType = (conditionIndex: number) => {
    return computed({
        get: () => props.group.conditions[conditionIndex]?.type || 'prerequisite',
        set: (value: string) => emit('update-condition-type', props.groupIndex, conditionIndex, value)
    });
};

// Type styling with proper colors and abbreviated labels
const conditionTypeColors = {
    prerequisite: 'bg-blue-100 text-blue-800 border-blue-200',
    co_requisite: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    concurrent_prerequisite: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    anti_requisite: 'bg-red-100 text-red-800 border-red-200',
    assumed_knowledge: 'bg-purple-100 text-purple-800 border-purple-200',
    credit_requirement: 'bg-orange-100 text-orange-800 border-orange-200',
};

const conditionTypeLabels = {
    prerequisite: 'Prerequisite (P)',
    co_requisite: 'Co-requisite (Co-req)',
    concurrent_prerequisite: 'Concurrent Prerequisites (Concurrent-req)',
    anti_requisite: 'Anti-requisite (A)',
    assumed_knowledge: 'Assumed Knowledge (AK)',
    credit_requirement: 'Credit Requirement',
};

const conditionTypeAbbreviations = {
    prerequisite: 'P',
    co_requisite: 'Co-req',
    concurrent_prerequisite: 'Concurrent-req',
    anti_requisite: 'A',
    assumed_knowledge: 'AK',
    credit_requirement: 'Credits',
};

// Helper text for each condition type
const conditionTypeHelp = {
    prerequisite: 'You must complete this unit before enrolling in a following unit.',
    co_requisite: 'You must complete this pair of units at the same time.',
    concurrent_prerequisite: 'You may complete these units either at the same time or before the higher unit.',
    anti_requisite: 'Unit(s) with similar content.',
    assumed_knowledge: 'Minimum level of knowledge needed for the unit. This does not prevent enrollment.',
    credit_requirement: 'Minimum credit points required.',
};
</script>

<template>
    <Card class="mb-2">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Badge :class="groupOperator === 'AND' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                        {{ groupOperator }}
                    </Badge>
                    <Select v-model="groupOperator">
                        <SelectTrigger class="w-20">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="AND">AND</SelectItem>
                            <SelectItem value="OR">OR</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-center space-x-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="$emit('remove-group', groupIndex)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
            <div class="mt-2">
                <Input
                    v-model="groupDescription"
                    placeholder="Group description (optional)"
                    class="text-sm"
                />
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Conditions -->
            <div class="space-y-4">
                <div
                    v-for="(condition, conditionIndex) in (group.conditions || [])"
                    :key="`condition-${conditionIndex}`"
                    class="border rounded-lg bg-white shadow-sm"
                >
                    <!-- Condition Header -->
                    <div class="flex items-center justify-between p-3 border-b bg-gray-50">
                        <div class="flex items-center space-x-3">
                            <Badge :class="conditionTypeColors[condition.type]" class="font-mono text-xs">
                                {{ conditionTypeAbbreviations[condition.type] }}
                            </Badge>
                            <Select v-model="getConditionType(conditionIndex).value">
                                <SelectTrigger class="w-64">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, type) in conditionTypeLabels"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="$emit('remove-condition', groupIndex, conditionIndex)"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Condition Content -->
                    <div class="p-4 space-y-3">
                        <!-- Help text -->
                        <div class="flex items-start space-x-2 text-sm text-gray-600 bg-blue-50 p-3 rounded-md">
                            <AlertCircle class="h-4 w-4 mt-0.5 text-blue-500" />
                            <p>{{ conditionTypeHelp[condition.type] }}</p>
                        </div>

                        <!-- Value selector based on type -->
                        <div>
                            <!-- Unit selector for unit-based conditions -->
                            <div v-if="['prerequisite', 'co_requisite', 'concurrent_prerequisite', 'anti_requisite'].includes(condition.type)">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Unit</label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-start h-12"
                                    @click="$emit('open-unit-search', groupIndex, conditionIndex)"
                                >
                                    <Search class="h-4 w-4 mr-2" />
                                    <span v-if="condition.unit" class="flex items-center space-x-2">
                                        <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ condition.unit.code }}</code>
                                        <span>{{ condition.unit.name }}</span>
                                        <span class="text-gray-500 text-sm">({{ condition.unit.credit_points }} CP)</span>
                                    </span>
                                    <span v-else class="text-gray-500">
                                        Click to select a unit...
                                    </span>
                                </Button>
                            </div>

                            <!-- Free text input for Assumed Knowledge -->
                            <div v-else-if="condition.type === 'assumed_knowledge'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Knowledge Description</label>
                                <Input
                                    v-model="getConditionText(conditionIndex).value"
                                    placeholder="e.g., Familiarity with boolean algebra and number systems"
                                    class="w-full"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    Describe the background knowledge needed. This will not prevent student enrollment.
                                </p>
                            </div>

                            <!-- Credit points input -->
                            <div v-else-if="condition.type === 'credit_requirement'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Required Credit Points</label>
                                <Input
                                    v-model.number="getConditionCredits(conditionIndex).value"
                                    type="number"
                                    placeholder="e.g., 60"
                                    min="0"
                                    class="w-full"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    Minimum credit points that must be completed before enrollment.
                                </p>
                            </div>
                        </div>

                        <!-- Current selection display -->
                        <div v-if="condition.unit || condition.free_text || condition.required_credits" class="p-3 bg-gray-50 rounded-md">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Current Requirement:</h4>
                            <div class="flex items-center space-x-2">
                                <Badge :class="conditionTypeColors[condition.type]" class="font-mono text-xs">
                                    {{ conditionTypeAbbreviations[condition.type] }}
                                </Badge>
                                <span v-if="condition.unit" class="text-sm">
                                    {{ condition.unit.code }} - {{ condition.unit.name }}
                                </span>
                                <span v-else-if="condition.required_credits" class="text-sm">
                                    {{ condition.required_credits }} credit points
                                </span>
                                <span v-else-if="condition.free_text" class="text-sm">
                                    {{ condition.free_text }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add condition button -->
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="$emit('add-condition', groupIndex)"
                    class="w-full border-dashed"
                >
                    <Plus class="h-4 w-4 mr-2" />
                    Add Condition
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
