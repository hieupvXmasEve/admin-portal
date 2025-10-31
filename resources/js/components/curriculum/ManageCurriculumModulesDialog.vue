<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Module } from '@/types/models';
import { router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface CurriculumModule {
    id: number;
    module_id: number;
    year_level: number | null;
    semester_number: number | null;
    is_required: boolean;
    group_name: string | null;
    order: number;
    note: string | null;
    module: Module;
}

interface ModuleRow {
    module_id: number;
    code: string;
    name: string;
    total_credits: number;
    year_level: number | null;
    semester_number: number | null;
    is_required: boolean;
    group_name: string | null;
    order: number;
    note: string | null;
}

interface Props {
    open: boolean;
    curriculumVersionId: number;
    selectedModules: CurriculumModule[];
    availableModules: Module[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    close: [];
}>();

const localModules = ref<ModuleRow[]>([]);
const selectedModuleIds = ref<Set<number>>(new Set());
const searchQuery = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            localModules.value = props.selectedModules.map((cm, index) => ({
                module_id: cm.module_id,
                code: cm.module.code,
                name: cm.module.name,
                total_credits: cm.module.total_credits,
                year_level: cm.year_level,
                semester_number: cm.semester_number,
                is_required: cm.is_required,
                group_name: cm.group_name,
                order: cm.order ?? index,
                note: cm.note,
            }));
            selectedModuleIds.value = new Set(props.selectedModules.map((cm) => cm.module_id));
        }
    },
);

const filteredAvailableModules = computed(() => {
    const query = searchQuery.value.toLowerCase();
    return props.availableModules.filter((module) => {
        const isNotSelected = !selectedModuleIds.value.has(module.id);
        const matchesSearch = module.code.toLowerCase().includes(query) || module.name.toLowerCase().includes(query);
        return isNotSelected && matchesSearch;
    });
});

const addModule = (module: Module) => {
    if (selectedModuleIds.value.has(module.id)) return;

    const maxOrder = localModules.value.length > 0 ? Math.max(...localModules.value.map((m) => m.order)) : -1;

    localModules.value.push({
        module_id: module.id,
        code: module.code,
        name: module.name,
        total_credits: module.total_credits,
        year_level: 1,
        semester_number: 1,
        is_required: true,
        group_name: 'core',
        order: maxOrder + 1,
        note: null,
    });

    selectedModuleIds.value.add(module.id);
    searchQuery.value = '';
};

const removeModule = (moduleId: number) => {
    localModules.value = localModules.value.filter((m) => m.module_id !== moduleId);
    selectedModuleIds.value.delete(moduleId);
};

const moveUp = (index: number) => {
    if (index === 0) return;
    const temp = localModules.value[index];
    localModules.value[index] = localModules.value[index - 1];
    localModules.value[index - 1] = temp;
    updateOrders();
};

const moveDown = (index: number) => {
    if (index === localModules.value.length - 1) return;
    const temp = localModules.value[index];
    localModules.value[index] = localModules.value[index + 1];
    localModules.value[index + 1] = temp;
    updateOrders();
};

const updateOrders = () => {
    localModules.value.forEach((module, index) => {
        module.order = index;
    });
};

const handleSave = () => {
    const data = {
        modules: localModules.value.map((module) => ({
            module_id: module.module_id,
            year_level: module.year_level,
            semester_number: module.semester_number,
            is_required: module.is_required,
            group_name: module.group_name,
            order: module.order,
            note: module.note,
        })),
    };

    router.post(route('curriculum-versions.modules.attach', props.curriculumVersionId), data, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Modules updated successfully');
            emit('update:open', false);
            emit('close');
        },
        onError: (errors) => {
            console.error('Error updating modules:', errors);
            toast.error('Failed to update modules');
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
        <DialogContent class="flex max-h-[90vh] !max-w-6xl flex-col">
            <DialogHeader>
                <DialogTitle>Manage Curriculum Modules</DialogTitle>
                <DialogDescription>Add modules to this curriculum version and configure their properties</DialogDescription>
            </DialogHeader>

            <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-hidden md:grid-cols-2">
                <!-- Selected Modules -->
                <div class="flex min-h-0 flex-col gap-2">
                    <Label class="font-semibold">Selected Modules ({{ localModules.length }})</Label>
                    <ScrollArea class="max-h-[calc(90vh-206px)] rounded-md border">
                        <div class="p-2">
                            <div v-if="localModules.length === 0" class="text-muted-foreground py-8 text-center text-sm">No modules selected. Add modules from the list.</div>
                            <div v-else class="space-y-2">
                                <div v-for="(module, index) in localModules" :key="module.module_id" class="bg-background rounded-md border p-3">
                                    <div class="flex items-start gap-2">
                                        <div class="flex flex-col gap-1">
                                            <Button variant="ghost" size="icon" class="h-5 w-5" :disabled="index === 0" @click="moveUp(index)">↑</Button>
                                            <Button variant="ghost" size="icon" class="h-5 w-5" :disabled="index === localModules.length - 1" @click="moveDown(index)">↓</Button>
                                        </div>

                                        <div class="flex-1 space-y-2">
                                            <div>
                                                <div class="text-sm font-medium">{{ module.code }} - {{ module.name }}</div>
                                                <div class="text-muted-foreground text-xs">{{ module.total_credits }} credits</div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <Label class="text-xs">Year Level</Label>
                                                    <Select :model-value="module.year_level?.toString()" @update:model-value="(val) => (module.year_level = val ? parseInt(val as string) : null)">
                                                        <SelectTrigger class="h-8 text-xs">
                                                            <SelectValue placeholder="Select" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="1">Year 1</SelectItem>
                                                            <SelectItem value="2">Year 2</SelectItem>
                                                            <SelectItem value="3">Year 3</SelectItem>
                                                            <SelectItem value="4">Year 4</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label class="text-xs">Semester</Label>
                                                    <Select :model-value="module.semester_number?.toString()" @update:model-value="(val) => (module.semester_number = val ? parseInt(val as string) : null)">
                                                        <SelectTrigger class="h-8 text-xs">
                                                            <SelectValue placeholder="Select" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="1">Semester 1</SelectItem>
                                                            <SelectItem value="2">Semester 2</SelectItem>
                                                            <SelectItem value="3">Semester 3</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-4">
                                                <div class="flex items-center gap-2">
                                                    <Checkbox :model-value="module.is_required" @update:model-value="(val: boolean | 'indeterminate') => (module.is_required = !!val)" />
                                                    <Label class="text-xs">Required</Label>
                                                </div>
                                                <div class="flex-1">
                                                    <Label class="text-xs">Group</Label>
                                                    <Input
                                                        :model-value="module.group_name ?? ''"
                                                        @update:model-value="(val: string | number) => (module.group_name = (typeof val === 'string' ? val : String(val)) || null)"
                                                        placeholder="core, elective..."
                                                        class="h-7 text-xs"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <Button variant="ghost" size="icon" class="h-7 w-7" @click="removeModule(module.module_id)">
                                            <Trash2 class="text-destructive h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </div>

                <!-- Available Modules -->
                <div class="flex min-h-0 flex-col gap-2">
                    <Label class="font-semibold">Available Modules</Label>
                    <Input v-model="searchQuery" placeholder="Search modules..." class="h-9" />
                    <ScrollArea class="max-h-[calc(90vh-250px)] rounded-md border">
                        <div class="p-2">
                            <div v-if="filteredAvailableModules.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                                {{ searchQuery ? 'No modules found matching your search.' : 'All available modules have been added.' }}
                            </div>
                            <div v-else class="space-y-1">
                                <Button v-for="module in filteredAvailableModules" :key="module.id" variant="ghost" class="h-auto w-full justify-start px-3 py-2 text-left" @click="addModule(module)">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium">{{ module.code }} - {{ module.name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ module.total_credits }} credits</div>
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
