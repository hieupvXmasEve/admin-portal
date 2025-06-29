<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useApi } from '@/composables/useApiRequest';
import { Plus, Search, X } from 'lucide-vue-next';
import { ref } from 'vue';
import PrerequisiteGroupCard from './PrerequisiteGroupCard.vue';

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
    initialGroups?: PrerequisiteGroup[];
    currentUnitId?: number;
}>();

const emit = defineEmits<{
    update: [groups: PrerequisiteGroup[]];
}>();

// Normalize groups to ensure all required properties are arrays
function normalizeGroups(inputGroups: PrerequisiteGroup[]): PrerequisiteGroup[] {
    return inputGroups.map((group) => normalizeGroup(group));
}

function normalizeGroup(group: PrerequisiteGroup): PrerequisiteGroup {
    return {
        ...group,
        conditions: Array.isArray(group.conditions) ? group.conditions : [],
    };
}

// State management
const groups = ref<PrerequisiteGroup[]>(normalizeGroups(props.initialGroups || []));
const unitSearchQuery = ref('');
const unitSearchResults = ref<Unit[]>([]);
const isSearchingUnits = ref(false);
const showUnitSearch = ref(false);
const currentConditionIndex = ref<{ groupIndex: number; conditionIndex: number } | null>(null);

// API instance
const api = useApi();

// Unit search functionality
let unitSearchTimeout: number;

const searchUnits = async (query: string) => {
    if (!query || query.length < 2) {
        unitSearchResults.value = [];
        return;
    }

    isSearchingUnits.value = true;

    try {
        // Get IDs of already selected units to exclude them
        const excludeIds = [
            props.currentUnitId, // Exclude the current unit being edited
            ...groups.value.flatMap((g: PrerequisiteGroup) => g.conditions.map((c) => c.required_unit_id)),
        ].filter(Boolean);

        const params = {
            q: query,
            exclude: excludeIds.join(','),
            limit: '10',
        };

        const result = await api.get('/api/units/search', params);

        if (result.data.value?.success) {
            unitSearchResults.value = result.data.value.data;
        } else {
            console.error('Unit search failed:', result.data.value?.message || 'Unknown error');
            unitSearchResults.value = [];
        }
    } catch (error: any) {
        console.error('Unit search failed:', error);
        unitSearchResults.value = [];
    } finally {
        isSearchingUnits.value = false;
    }
};

const watchUnitSearch = (newQuery: string) => {
    clearTimeout(unitSearchTimeout);
    unitSearchResults.value = [];

    if (newQuery && newQuery.length >= 2) {
        unitSearchTimeout = setTimeout(() => {
            searchUnits(newQuery);
        }, 300);
    }
};

// Group management
const addRootGroup = () => {
    groups.value.push({
        logic_operator: 'AND',
        description: '',
        conditions: [],
    });
    emitUpdate();
};

const removeGroup = (groupIndex: number) => {
    groups.value.splice(groupIndex, 1);
    emitUpdate();
};

const updateGroupOperator = (groupIndex: number, operator: 'AND' | 'OR') => {
    if (groups.value[groupIndex]) {
        groups.value[groupIndex].logic_operator = operator;
        emitUpdate();
    }
};

const updateGroupDescription = (groupIndex: number, description: string) => {
    if (groups.value[groupIndex]) {
        groups.value[groupIndex].description = description;
        emitUpdate();
    }
};

// Condition management
const addCondition = (groupIndex: number) => {
    if (groups.value[groupIndex]) {
        groups.value[groupIndex].conditions.push({
            type: 'prerequisite',
            required_unit_id: undefined,
            unit: undefined,
            required_credits: undefined,
            free_text: '',
        });
        emitUpdate();
    }
};

const removeCondition = (groupIndex: number, conditionIndex: number) => {
    if (groups.value[groupIndex]) {
        groups.value[groupIndex].conditions.splice(conditionIndex, 1);
        emitUpdate();
    }
};

const updateConditionType = (groupIndex: number, conditionIndex: number, type: string) => {
    const group = groups.value[groupIndex];
    if (group && group.conditions[conditionIndex]) {
        group.conditions[conditionIndex].type = type as PrerequisiteCondition['type'];

        // Reset other fields based on type
        if (type === 'credit_requirement') {
            group.conditions[conditionIndex].required_unit_id = undefined;
            group.conditions[conditionIndex].unit = undefined;
            group.conditions[conditionIndex].free_text = '';
            group.conditions[conditionIndex].required_credits = group.conditions[conditionIndex].required_credits || 0;
        } else if (type === 'assumed_knowledge') {
            group.conditions[conditionIndex].required_unit_id = undefined;
            group.conditions[conditionIndex].unit = undefined;
            group.conditions[conditionIndex].required_credits = undefined;
            // Keep free_text for assumed knowledge
        } else {
            // For unit-based conditions (prerequisite, co_requisite, concurrent_prerequisite, anti_requisite)
            group.conditions[conditionIndex].required_credits = undefined;
            group.conditions[conditionIndex].free_text = '';
        }

        emitUpdate();
    }
};

const openUnitSearchForCondition = (groupIndex: number, conditionIndex: number) => {
    currentConditionIndex.value = { groupIndex, conditionIndex };
    showUnitSearch.value = true;
    unitSearchQuery.value = '';
    unitSearchResults.value = [];
};

const selectUnitForCondition = (unit: Unit) => {
    if (currentConditionIndex.value) {
        const group = groups.value[currentConditionIndex.value.groupIndex];
        if (group && group.conditions[currentConditionIndex.value.conditionIndex]) {
            group.conditions[currentConditionIndex.value.conditionIndex].required_unit_id = unit.id;
            group.conditions[currentConditionIndex.value.conditionIndex].unit = unit;
            emitUpdate();
        }
    }
    showUnitSearch.value = false;
    currentConditionIndex.value = null;
};

const updateConditionCredits = (groupIndex: number, conditionIndex: number, credits: number) => {
    const group = groups.value[groupIndex];
    if (group && group.conditions[conditionIndex]) {
        group.conditions[conditionIndex].required_credits = credits;
        emitUpdate();
    }
};

const updateConditionText = (groupIndex: number, conditionIndex: number, text: string) => {
    const group = groups.value[groupIndex];
    if (group && group.conditions[conditionIndex]) {
        group.conditions[conditionIndex].free_text = text;
        emitUpdate();
    }
};

// Utility functions
const emitUpdate = () => {
    console.log('groups.value', groups.value);
    emit('update', groups.value);
};

// Initialize with a default group if empty
if (groups.value.length === 0) {
    addRootGroup();
}
</script>

<template>
    <div class="space-y-6">
        <!-- Examples Section -->
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h4 class="mb-3 flex items-center text-sm font-semibold text-blue-800">
                <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd"
                    />
                </svg>
                Prerequisite Type Examples
            </h4>
            <div class="space-y-2 text-sm text-blue-700">
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center rounded border-purple-200 bg-purple-100 px-2 py-1 font-mono text-xs text-purple-800"
                        >AK</span
                    >
                    <span>Familiarity with boolean algebra and number systems</span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center rounded border-yellow-200 bg-yellow-100 px-2 py-1 font-mono text-xs text-yellow-800"
                        >Concurrent-req</span
                    >
                    <span>COS10009 OR</span>
                    <span class="inline-flex items-center rounded border-blue-200 bg-blue-100 px-2 py-1 font-mono text-xs text-blue-800">P</span>
                    <span>ICT10001</span>
                </div>
                <div class="mt-2 text-xs text-blue-600">
                    <strong>Types:</strong> P (Prerequisite), Co-req (Co-requisite), Concurrent-req (Concurrent), A (Anti-requisite), AK (Assumed
                    Knowledge)
                </div>
            </div>
        </div>

        <!-- Main Interface -->
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Prerequisite Groups</h3>
                <Button type="button" variant="outline" size="sm" @click="addRootGroup">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Group
                </Button>
            </div>

            <!-- Groups -->
            <div class="space-y-4">
                <div v-for="(group, groupIndex) in groups" :key="`group-${groupIndex}`" class="rounded-lg border">
                    <PrerequisiteGroupCard
                        :group="group"
                        :group-index="groupIndex"
                        @remove-group="removeGroup"
                        @update-operator="updateGroupOperator"
                        @update-description="updateGroupDescription"
                        @add-condition="addCondition"
                        @remove-condition="removeCondition"
                        @update-condition-type="updateConditionType"
                        @update-condition-credits="updateConditionCredits"
                        @update-condition-text="updateConditionText"
                        @open-unit-search="openUnitSearchForCondition"
                    />
                </div>
            </div>
        </div>

        <!-- Unit Search Modal -->
        <div v-if="showUnitSearch" class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black">
            <div class="mx-4 max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Search Units</h3>
                    <Button variant="ghost" size="sm" @click="showUnitSearch = false">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input
                            v-model="unitSearchQuery"
                            @input="watchUnitSearch(unitSearchQuery)"
                            placeholder="Search by unit code or name..."
                            class="pl-9"
                            autofocus
                        />
                    </div>

                    <div v-if="isSearchingUnits" class="py-4 text-center">
                        <div class="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                        <p class="mt-2 text-sm text-gray-500">Searching units...</p>
                    </div>

                    <div v-else-if="unitSearchResults.length > 0" class="max-h-60 space-y-2 overflow-y-auto">
                        <div
                            v-for="unit in unitSearchResults"
                            :key="unit.id"
                            class="cursor-pointer rounded-lg border p-3 hover:bg-gray-50"
                            @click="selectUnitForCondition(unit)"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <code class="font-mono text-sm">{{ unit.code }}</code>
                                    <p class="text-sm text-gray-600">{{ unit.name }}</p>
                                    <p class="text-xs text-gray-500">{{ unit.credit_points }} CP</p>
                                </div>
                                <Button variant="ghost" size="sm">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="unitSearchQuery.length >= 2" class="py-4 text-center">
                        <p class="text-sm text-gray-500">No units found matching "{{ unitSearchQuery }}"</p>
                    </div>

                    <div v-else class="py-4 text-center">
                        <p class="text-sm text-gray-500">Type at least 2 characters to search for units</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
