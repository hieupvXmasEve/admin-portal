<script setup lang="ts">
import DebouncedInput from '@/components/DebouncedInput.vue';
import PrerequisiteGroupsManager from '@/components/PrerequisiteGroupsManager.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApiRequest';
import { useModuleNavigation } from '@/composables/useModuleNavigation';
import type { EquivalentUnit, PrerequisiteGroup, Unit } from '@/types/Unit';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Search, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface UnitData {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    prerequisite_groups?: PrerequisiteGroup[];
}

// interface EditRestriction {
//     field: string;
//     reason: string;
// }

const props = defineProps<{
    unit: UnitData;
    // editRestrictions: EditRestriction[];
    prerequisiteDescriptions?: string | null;
}>();

// Form handling
const form = useForm({
    code: props.unit.code,
    name: props.unit.name,
    credit_points: props.unit.credit_points,
});

// Separate reactive data for relationships
const prerequisiteGroups = ref<PrerequisiteGroup[]>(props.unit.prerequisite_groups || []);
const equivalentUnits = ref<EquivalentUnit[]>([]);

// Real-time validation
const codeValidation = ref<{ valid: boolean; message: string } | null>(null);
const isValidatingCode = ref(false);

// Unit search for equivalents
const unitSearchQuery = ref('');
const unitSearchResults = ref<Unit[]>([]);
const isSearchingUnits = ref(false);
const showUnitSearch = ref(false);

// API instance
const api = useApi();

// Module navigation với return param
const { navigateBack, getReturnUrl } = useModuleNavigation({
    moduleIndexRoute: 'units.index',
});

// Check if field is restricted
// const isFieldRestricted = (fieldName: string) => {
//     return props.editRestrictions.some((restriction) => restriction.field === fieldName);
// };

// const getFieldRestrictionReason = (fieldName: string) => {
//     const restriction = props.editRestrictions.find((r) => r.field === fieldName);
//     return restriction?.reason || '';
// };

const validateCode = async (code: string) => {
    if (!code || code.length < 2 || code === props.unit.code) {
        codeValidation.value = null;
        return;
    }

    isValidatingCode.value = true;

    try {
        const result = await api.post('/units/validate-code', {
            code: code,
            unit_id: props.unit.id,
        });

        if (result.data.value?.success) {
            codeValidation.value = result.data.value.data;
        } else {
            console.error('Code validation failed:', result.data.value?.message || 'Unknown error');
            codeValidation.value = null;
        }
    } catch (error: any) {
        console.error('Code validation failed:', error);
        codeValidation.value = null;
    } finally {
        isValidatingCode.value = false;
    }
};

const searchUnits = async (query: string) => {
    if (!query || query.length < 2) {
        unitSearchResults.value = [];
        return;
    }

    isSearchingUnits.value = true;

    try {
        // Get IDs of already selected units to exclude them
        const excludeIds = [
            props.unit.id, // Exclude the current unit being edited
            ...prerequisiteGroups.value.flatMap((g: PrerequisiteGroup) => g.conditions.map((c) => c.required_unit_id)),
            ...equivalentUnits.value.map((e: EquivalentUnit) => e.unit.id),
        ].filter(Boolean);

        const params = {
            q: query,
            exclude: excludeIds.join(','),
            limit: '10',
        };

        const result = await api.get('/units/search', params);

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

// Debounced code validation handler
const handleCodeChange = (value: string | number) => {
    const stringValue = String(value);
    const formattedCode = stringValue.toUpperCase().replace(/[^A-Z0-9]/g, '');
    // if (!isFieldRestricted('code')) {
    form.code = formattedCode;
    if (formattedCode && formattedCode.length >= 2 && formattedCode !== props.unit.code) {
        validateCode(formattedCode);
    } else {
        codeValidation.value = null;
    }
    // }
};

// Debounced unit search handler
const handleUnitSearch = (value: string | number) => {
    const query = String(value);
    unitSearchQuery.value = query;
    searchUnits(query);
};

// Form submission
const handleSubmit = () => {
    // Create a base form data object
    const formData: any = {
        ...form.data(),
        prerequisite_groups: prerequisiteGroups.value,
    };
    // Add equivalent units if any
    if (equivalentUnits.value.length > 0) {
        formData.equivalent_units = equivalentUnits.value.map((e: EquivalentUnit) => ({
            equivalent_unit_id: e.unit.id,
            reason: e.reason || null,
        }));
    }

    // Add return parameter if exists
    const returnUrl = getReturnUrl();
    if (returnUrl) {
        formData.return = returnUrl;
    }

    router.put(`/units/${props.unit.id}`, formData, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Unit updated successfully');
        },
        onError: () => {
            toast.error('Unit update failed');
        },
    });
};

// Validate credit points
const validateCreditPoints = (value: string) => {
    const num = parseFloat(value);
    if (isNaN(num)) {
        form.credit_points = 0;
    } else {
        form.credit_points = Math.max(0, Math.min(999.99, Math.round(num * 100) / 100));
    }
};

// Equivalent units management
const addEquivalentUnit = (unit: Unit, reason: string = '') => {
    // Check if unit already exists
    const exists = equivalentUnits.value.some((e) => e.unit.id === unit.id);
    if (!exists) {
        equivalentUnits.value.push({ unit, reason });
        unitSearchQuery.value = '';
        unitSearchResults.value = [];
        showUnitSearch.value = false;
    }
};

// const removeEquivalentUnit = (index: number) => {
//     equivalentUnits.value.splice(index, 1);
// };

// const updateEquivalentReason = (index: number, reason: string) => {
//     equivalentUnits.value[index].reason = reason;
// };

// Search management
const openUnitSearch = () => {
    showUnitSearch.value = true;
    unitSearchQuery.value = '';
    unitSearchResults.value = [];
};

const selectUnit = (unit: Unit) => {
    addEquivalentUnit(unit);
};

// URL helpers
// const indexWithCurrentQuery = (): string => {
//     const search = typeof window !== 'undefined' ? window.location.search : '';
//     return `${curriculumRoutes.units.index()}${search || ''}`;
// };
</script>

<template>
    <Head :title="`Edit Unit - ${unit.code}`" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Edit Unit - {{ unit.code }}</h1>
        <Button variant="outline" size="sm" @click="navigateBack">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
        </Button>
    </div>

    <!-- Edit Restrictions Warning -->
    <!-- <div v-if="editRestrictions.length > 0" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
        <div class="flex items-start">
            <AlertTriangle class="mt-0.5 mr-3 h-5 w-5 text-yellow-400" />
            <div>
                <h3 class="text-sm font-medium text-yellow-800">Edit Restrictions</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p>Some fields cannot be edited due to active relationships:</p>
                    <ul class="mt-1 list-inside list-disc">
                        <li v-for="restriction in editRestrictions" :key="restriction.field">
                            <strong>{{ restriction.field.replace('_', ' ').toUpperCase() }}</strong
                            >:
                            {{ restriction.reason }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Form -->
    <div class="mx-auto w-full max-w-4xl">
        <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- Basic Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Unit Information</CardTitle>
                    <CardDescription> Update the unit information. Some fields may be restricted based on current usage. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Unit Code -->
                    <div class="space-y-2">
                        <Label for="code">Unit Code *</Label>
                        <div class="relative">
                            <DebouncedInput
                                id="code"
                                :model-value="form.code"
                                @debounced="handleCodeChange"
                                placeholder="e.g., CS101, MATH201"
                                :class="{
                                    'border-red-500': form.errors.code || codeValidation?.valid === false,
                                    'border-green-500': codeValidation?.valid === true,
                                }"
                                :debounce="500"
                                maxlength="20"
                                required
                            />
                            <div v-if="isValidatingCode" class="absolute top-1/2 right-3 -translate-y-1/2">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        </div>

                        <!-- Field restriction message -->
                        <!-- <p v-if="isFieldRestricted('code')" class="text-sm text-amber-600">
                            {{ getFieldRestrictionReason('code') }}
                        </p> -->

                        <!-- Code validation feedback -->
                        <div v-if="codeValidation" class="text-sm">
                            <p
                                :class="{
                                    'text-green-600': codeValidation.valid,
                                    'text-red-600': !codeValidation.valid,
                                }"
                            >
                                {{ codeValidation.message }}
                            </p>
                        </div>

                        <!-- Server validation errors -->
                        <p v-if="form.errors.code" class="text-sm text-red-600">
                            {{ form.errors.code }}
                        </p>

                        <!-- <p v-if="!isFieldRestricted('code')" class="text-sm text-gray-500">Unit code must be 2-20 characters, uppercase letters and numbers only.</p> -->
                    </div>

                    <!-- Unit Name -->
                    <div class="space-y-2">
                        <Label for="name">Unit Name *</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g., Introduction to Computer Science"
                            :class="{
                                'border-red-500': form.errors.name,
                            }"
                            maxlength="255"
                            required
                        />
                        <!-- <p v-if="isFieldRestricted('name')" class="text-sm text-amber-600">
                            {{ getFieldRestrictionReason('name') }}
                        </p> -->
                        <p v-if="form.errors.name" class="text-sm text-red-600">
                            {{ form.errors.name }}
                        </p>
                        <!-- <p v-if="!isFieldRestricted('name')" class="text-sm text-gray-500">Enter the full name of the unit (3-255 characters).</p> -->
                    </div>

                    <!-- Credit Points -->
                    <div class="space-y-2">
                        <Label for="credit_points">Credit Points *</Label>
                        <Input
                            id="credit_points"
                            v-model.number="form.credit_points"
                            @input="validateCreditPoints($event.target.value)"
                            type="number"
                            step="1"
                            min="0"
                            max="20"
                            placeholder="3.00"
                            :class="{
                                'border-red-500': form.errors.credit_points,
                            }"
                            required
                        />
                        <!-- <p v-if="isFieldRestricted('credit_points')" class="text-sm text-amber-600">
                            {{ getFieldRestrictionReason('credit_points') }}
                        </p> -->
                        <p v-if="form.errors.credit_points" class="text-sm text-red-600">
                            {{ form.errors.credit_points }}
                        </p>
                        <!-- <p v-if="!isFieldRestricted('credit_points')" class="text-sm text-gray-500">Credit points must be between 0.25 and 999.99 (e.g., 3.00, 6.00).</p> -->
                    </div>
                </CardContent>
            </Card>

            <!-- Prerequisites -->
            <Card>
                <CardHeader>
                    <CardTitle>Prerequisites</CardTitle>
                    <CardDescription>
                        Define prerequisite requirements using five types: P (Prerequisite), Co-req (Co-requisite), Concurrent-req (Concurrent), A (Anti-requisite), and AK (Assumed Knowledge). Group conditions with AND/OR logic operators for complex
                        requirements.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Current Prerequisites -->
                    <div v-if="prerequisiteDescriptions" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <h4 class="mb-2 text-sm font-semibold text-blue-800">Current Prerequisites Summary:</h4>
                        <pre class="text-sm font-medium whitespace-pre-wrap text-blue-700">{{ prerequisiteDescriptions }}</pre>
                    </div>

                    <PrerequisiteGroupsManager :initial-groups="prerequisiteGroups" :current-unit-id="unit.id" @update="(groups) => (prerequisiteGroups = groups)" />
                </CardContent>
            </Card>

            <!-- Equivalent Units -->
            <Card>
                <CardHeader>
                    <CardTitle>Equivalent Units</CardTitle>
                    <CardDescription> Specify units that this unit replaces or is equivalent to (optional). </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Add Equivalent Unit Button -->
                    <Button type="button" variant="outline" @click="openUnitSearch()" class="w-full sm:w-auto">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Equivalent Unit
                    </Button>

                    <!-- Selected Equivalent Units -->
                    <!-- <div v-if="equivalentUnits.length > 0" class="space-y-3">
                        <div v-for="(equivalent, index) in equivalentUnits" :key="equivalent.unit.id" class="space-y-3 rounded-lg border bg-gray-50 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="mb-1 flex items-center gap-2">
                                        <code class="rounded bg-white px-2 py-1 font-mono text-sm">
                                            {{ equivalent.unit.code }}
                                        </code>
                                        <Badge class="bg-purple-100 text-purple-800"> Equivalent</Badge>
                                    </div>
                                    <p class="text-sm text-gray-600">{{ equivalent.unit.name }}</p>
                                    <p class="text-xs text-gray-500">{{ equivalent.unit.credit_points }} CP</p>
                                </div>
                                <Button type="button" variant="ghost" size="sm" @click="removeEquivalentUnit(index)">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                            <div>
                                <Label :for="`reason-${index}`" class="text-xs">Reason (optional)</Label>
                                <Input :id="`reason-${index}`" v-model="equivalent.reason" @input="updateEquivalentReason(index, $event.target.value)" placeholder="e.g., Curriculum update, Content overlap" class="mt-1" />
                            </div>
                        </div>
                    </div> -->

                    <!-- <p v-else class="text-sm text-gray-500 italic">No equivalent units specified. This unit does not replace any existing units.</p> -->
                </CardContent>
            </Card>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-4 pt-6">
                <Button type="button" variant="outline" @click="navigateBack" :disabled="form.processing"> Cancel </Button>
                <Button type="submit" :disabled="form.processing || codeValidation?.valid === false">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </form>
    </div>

    <!-- Unit Search Modal -->
    <div v-if="showUnitSearch" class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black">
        <div class="mx-4 max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Search Units - Equivalent Units</h3>
                <Button variant="ghost" size="sm" @click="showUnitSearch = false">
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <div class="space-y-4">
                <div class="relative">
                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <DebouncedInput v-model="unitSearchQuery" @debounced="handleUnitSearch" placeholder="Search by unit code or name..." class="pl-9" autofocus />
                </div>

                <div v-if="isSearchingUnits" class="py-4 text-center">
                    <div class="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                    <p class="mt-2 text-sm text-gray-500">Searching units...</p>
                </div>

                <div v-else-if="unitSearchResults.length > 0" class="max-h-60 space-y-2 overflow-y-auto">
                    <div v-for="unit in unitSearchResults" :key="unit.id" class="cursor-pointer rounded-lg border p-3 hover:bg-gray-50" @click="selectUnit(unit)">
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
</template>
