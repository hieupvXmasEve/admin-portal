<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Unit } from '@/types/models';
import { router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface UnitWithPivot extends Unit {
    pivot?: {
        grading_type: 'grade' | 'pass_fail';
        weight: number | null;
        order: number;
    };
}

interface Props {
    open: boolean;
    moduleId: number;
    selectedUnits: UnitWithPivot[];
    availableUnits: Unit[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    close: [];
}>();

interface UnitRow {
    unit_id: number;
    code: string;
    name: string;
    credit_points: number;
    grading_type: 'grade' | 'pass_fail';
    weight: number | null;
    order: number;
}

const localUnits = ref<UnitRow[]>([]);
const selectedUnitIds = ref<Set<number>>(new Set());
const searchQuery = ref('');

// Initialize local units when dialog opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            localUnits.value = props.selectedUnits.map((unit, index) => ({
                unit_id: unit.id,
                code: unit.code,
                name: unit.name,
                credit_points: unit.credit_points,
                grading_type: unit.pivot?.grading_type || 'grade',
                weight: unit.pivot?.weight || null,
                order: unit.pivot?.order ?? index,
            }));
            selectedUnitIds.value = new Set(props.selectedUnits.map((u) => u.id));
        }
    },
);

const filteredAvailableUnits = computed(() => {
    const query = searchQuery.value.toLowerCase();
    return props.availableUnits.filter((unit) => {
        const isNotSelected = !selectedUnitIds.value.has(unit.id);
        const matchesSearch = unit.code.toLowerCase().includes(query) || unit.name.toLowerCase().includes(query);
        return isNotSelected && matchesSearch;
    });
});

const addUnit = (unit: Unit) => {
    if (selectedUnitIds.value.has(unit.id)) return;

    const maxOrder = localUnits.value.length > 0 ? Math.max(...localUnits.value.map((u) => u.order)) : -1;

    localUnits.value.push({
        unit_id: unit.id,
        code: unit.code,
        name: unit.name,
        credit_points: unit.credit_points,
        grading_type: 'grade',
        weight: null,
        order: maxOrder + 1,
    });

    selectedUnitIds.value.add(unit.id);
    searchQuery.value = '';
};

const removeUnit = (unitId: number) => {
    localUnits.value = localUnits.value.filter((u) => u.unit_id !== unitId);
    selectedUnitIds.value.delete(unitId);
};

const moveUp = (index: number) => {
    if (index === 0) return;
    const temp = localUnits.value[index];
    localUnits.value[index] = localUnits.value[index - 1];
    localUnits.value[index - 1] = temp;
    updateOrders();
};

const moveDown = (index: number) => {
    if (index === localUnits.value.length - 1) return;
    const temp = localUnits.value[index];
    localUnits.value[index] = localUnits.value[index + 1];
    localUnits.value[index + 1] = temp;
    updateOrders();
};

const updateOrders = () => {
    localUnits.value.forEach((unit, index) => {
        unit.order = index;
    });
};

const handleSave = () => {
    const data = {
        units: localUnits.value.map((unit) => ({
            unit_id: unit.unit_id,
            grading_type: unit.grading_type,
            weight: unit.weight,
            order: unit.order,
        })),
    };

    router.post(route('admin.modules.sync-units', props.moduleId), data, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Units updated successfully');
            emit('update:open', false);
            emit('close');
        },
        onError: (errors) => {
            console.error('Error updating units:', errors);
            toast.error('Failed to update units');
        },
    });
};

const handleClose = () => {
    emit('update:open', false);
    emit('close');
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="flex max-h-[90vh] min-h-[90vh] !max-w-4xl flex-col">
            <DialogHeader>
                <DialogTitle>Manage Module Units</DialogTitle>
                <DialogDescription> Add, remove, and reorder units for this module. Set weights for graded units if needed. </DialogDescription>
            </DialogHeader>

            <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-hidden md:grid-cols-2">
                <!-- Selected Units -->
                <div class="flex min-h-0 flex-col gap-2">
                    <Label class="font-semibold">Selected Units ({{ localUnits.length }})</Label>
                    <ScrollArea class="max-h-[calc(90vh-206px)] rounded-md border">
                        <div class="p-2">
                            <div v-if="localUnits.length === 0" class="text-muted-foreground py-8 text-center text-sm">No units selected. Add units from the list.</div>
                            <div v-else class="space-y-2">
                                <div v-for="(unit, index) in localUnits" :key="unit.unit_id" class="bg-background flex items-center gap-2 rounded-md border p-2">
                                    <div class="flex flex-col gap-1">
                                        <Button variant="ghost" size="icon" class="h-5 w-5" :disabled="index === 0" @click="moveUp(index)"> ↑ </Button>
                                        <Button variant="ghost" size="icon" class="h-5 w-5" :disabled="index === localUnits.length - 1" @click="moveDown(index)"> ↓ </Button>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium">{{ unit.code }}</div>
                                        <div class="text-muted-foreground truncate text-xs">{{ unit.name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ unit.credit_points }} credits</div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="w-28">
                                            <Label class="text-xs">Grading Type</Label>
                                            <Select v-model="unit.grading_type">
                                                <SelectTrigger class="h-8 text-sm">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="grade">Graded</SelectItem>
                                                    <SelectItem value="pass_fail">Pass/Fail</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="w-20">
                                            <Label class="text-xs">Weight</Label>
                                            <Input
                                                :model-value="unit.weight ?? undefined"
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                placeholder="1.0"
                                                class="h-8 text-sm"
                                                :disabled="unit.grading_type === 'pass_fail'"
                                                @update:model-value="(val) => (unit.weight = val === '' || val === undefined ? null : Number(val))"
                                            />
                                        </div>
                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="removeUnit(unit.unit_id)">
                                            <Trash2 class="text-destructive h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </div>

                <!-- Available Units -->
                <div class="flex min-h-0 flex-col gap-2">
                    <Label class="font-semibold">Available Units</Label>
                    <Input v-model="searchQuery" placeholder="Search units..." class="h-9" />
                    <ScrollArea class="max-h-[calc(90vh-250px)] rounded-md border">
                        <div class="p-2">
                            <div v-if="filteredAvailableUnits.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                                {{ searchQuery ? 'No units found matching your search.' : 'All available units have been added.' }}
                            </div>
                            <div v-else class="space-y-1">
                                <Button v-for="unit in filteredAvailableUnits" :key="unit.id" variant="ghost" class="h-auto w-full justify-start px-3 py-2 text-left" @click="addUnit(unit)">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium">{{ unit.code }}</div>
                                        <div class="text-muted-foreground truncate text-xs">{{ unit.name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ unit.credit_points }} credits</div>
                                    </div>
                                </Button>
                            </div>
                        </div>
                    </ScrollArea>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleClose">Cancel</Button>
                <Button @click="handleSave">Save Changes</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
