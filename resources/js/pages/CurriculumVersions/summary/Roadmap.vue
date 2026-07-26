<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

interface PrerequisiteCondition {
    type: string;
    required_unit_id: number;
    required_unit_code: string;
}

interface PrerequisiteGroup {
    logic_operator: string;
    conditions: PrerequisiteCondition[];
}

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    prerequisite_groups: PrerequisiteGroup[];
}

interface CurriculumUnit {
    id: number;
    unit_id: number;
    semester_number: number;
    year_level: number;
    unit: Unit | null;
}

interface CurriculumVersion {
    id: number;
    version_code: string;
    notes?: string;
    created_at: string;
    has_modular_structure?: boolean;
    effective_from_semester?: {
        id: number;
        name: string;
        code: string;
    } | null;
    program: { name: string; code: string };
    specialization: { name: string; code: string };
    curriculum_units: CurriculumUnit[];
}

const props = defineProps<{
    curriculumVersion: CurriculumVersion;
}>();

// --- State ---
const containerRef = ref<HTMLElement | null>(null);
const unitRefs = ref<Record<number, HTMLElement>>({});
const lines = ref<
    {
        id: string;
        x1: number;
        y1: number;
        x2: number;
        y2: number;
        type: string;
        fromId: number;
        toId: number;
    }[]
>([]);

const activeUnitId = ref<number | null>(null);
const ancestorIds = ref<Set<number>>(new Set());
const descendantIds = ref<Set<number>>(new Set());

// --- Computed Data ---

// Group units by semester
const semesters = computed(() => {
    const groups: Record<number, CurriculumUnit[]> = {};
    // Find absolute max semester to fill gaps if desired, or just sparse
    props.curriculumVersion.curriculum_units.forEach((cu) => {
        if (!groups[cu.semester_number]) groups[cu.semester_number] = [];
        groups[cu.semester_number].push(cu);
    });

    // Sort semesters keys and return array of objects
    return Object.keys(groups)
        .map(Number)
        .sort((a, b) => a - b)
        .map((num) => ({
            number: num,
            units: groups[num].sort((a, b) => a.year_level - b.year_level || (a.unit?.code.localeCompare(b.unit?.code ?? '') ?? 0)),
        }));
});

// Map unit_id to curriculum_unit objects for easy lookup
const unitMap = computed(() => {
    const map = new Map<number, CurriculumUnit>();
    props.curriculumVersion.curriculum_units.forEach((cu) => {
        if (cu.unit_id) map.set(cu.unit_id, cu);
    });
    return map;
});

// --- Actions ---

const setUnitRef = (el: any, unitId: number) => {
    if (el && el.$el) {
        unitRefs.value[unitId] = el.$el;
    } else if (el) {
        unitRefs.value[unitId] = el;
    }
};

const updateLines = () => {
    if (!containerRef.value) return;

    const newLines: typeof lines.value = [];
    const containerRect = containerRef.value.getBoundingClientRect();
    const scrollLeft = containerRef.value.scrollLeft;
    const scrollTop = containerRef.value.scrollTop;

    // Iterate through all units to find their prerequisites
    props.curriculumVersion.curriculum_units.forEach((cu) => {
        if (!cu.unit) return;
        const targetEl = unitRefs.value[cu.unit_id];
        if (!targetEl) return;

        const targetRect = targetEl.getBoundingClientRect();

        // Calculate "Target" connection point (Left center)
        // Relative to container content (accounting for scroll)
        const x2 = targetRect.left - containerRect.left + scrollLeft;
        const y2 = targetRect.top - containerRect.top + scrollTop + targetRect.height / 2;

        cu.unit.prerequisite_groups.forEach((group) => {
            group.conditions.forEach((cond) => {
                if (!cond.required_unit_id) return;

                // Check if the prerequisite unit exists in this curriculum
                // (It might be a unit implementation detail that is not in the displayed graph)
                if (!unitMap.value.has(cond.required_unit_id)) return;

                const sourceEl = unitRefs.value[cond.required_unit_id];
                if (!sourceEl) return;

                const sourceRect = sourceEl.getBoundingClientRect();

                // Calculate "Source" connection point (Right center)
                const x1 = sourceRect.right - containerRect.left + scrollLeft;
                const y1 = sourceRect.top - containerRect.top + scrollTop + sourceRect.height / 2;

                newLines.push({
                    id: `${cond.required_unit_id}-${cu.unit_id}`,
                    x1,
                    y1,
                    x2,
                    y2,
                    type: cond.type, //'prerequisite', 'co_requisite'
                    fromId: cond.required_unit_id,
                    toId: cu.unit_id!,
                });
            });
        });
    });

    lines.value = newLines;
};

// --- Highlight Logic ---

const handleUnitClick = (unitId: number) => {
    if (activeUnitId.value === unitId) {
        // Deselect
        activeUnitId.value = null;
        ancestorIds.value.clear();
        descendantIds.value.clear();
        return;
    }

    activeUnitId.value = unitId;
    const ancestors = new Set<number>();
    const descendants = new Set<number>();

    // 1. Find Ancestors (Prerequisites)
    const findAncestors = (currentId: number) => {
        const cu = unitMap.value.get(currentId);
        if (!cu?.unit) return;

        cu.unit.prerequisite_groups.forEach((group) => {
            group.conditions.forEach((cond) => {
                if (cond.required_unit_id && unitMap.value.has(cond.required_unit_id)) {
                    if (!ancestors.has(cond.required_unit_id)) {
                        ancestors.add(cond.required_unit_id);
                        findAncestors(cond.required_unit_id);
                    }
                }
            });
        });
    };

    // 2. Find Descendants (Dependents)
    const findDescendants = (currentId: number) => {
        props.curriculumVersion.curriculum_units.forEach((cu) => {
            // Avoid cycles or re-processing
            if (!cu.unit || descendants.has(cu.unit_id!) || cu.unit_id === activeUnitId.value || ancestors.has(cu.unit_id!)) return;

            let isDependent = false;
            for (const group of cu.unit.prerequisite_groups) {
                for (const cond of group.conditions) {
                    if (cond.required_unit_id === currentId) {
                        isDependent = true;
                        break;
                    }
                }
                if (isDependent) break;
            }

            if (isDependent) {
                descendants.add(cu.unit_id!);
                findDescendants(cu.unit_id!);
            }
        });
    };

    findAncestors(unitId);
    findDescendants(unitId);

    ancestorIds.value = ancestors;
    descendantIds.value = descendants;
};

const getUnitState = (unitId: number) => {
    if (activeUnitId.value === unitId) return 'active';
    if (ancestorIds.value.has(unitId)) return 'ancestor';
    if (descendantIds.value.has(unitId)) return 'descendant';
    return null;
};

const getCardStyle = (unitId: number) => {
    if (activeUnitId.value === null) return 'opacity-100 hover:border-slate-400';

    const state = getUnitState(unitId);
    if (state === 'active') return 'opacity-100 z-20 scale-105 ring-2 ring-blue-600 shadow-xl border-blue-200 bg-blue-50';
    if (state === 'ancestor') return 'opacity-100 z-10 scale-100 ring-1 ring-amber-500 border-amber-200 bg-amber-50';
    if (state === 'descendant') return 'opacity-100 z-10 scale-100 ring-1 ring-emerald-500 border-emerald-200 bg-emerald-50';

    return 'opacity-20 grayscale blur-[1px] transition-all duration-300';
};

const getLineColor = (line: (typeof lines.value)[0]) => {
    if (activeUnitId.value === null) return '#94a3b8'; // Slate 400

    // Line part of Ancestor chain (Incoming to active or internal to ancestor chain)
    // 1. Line ends at Active (Direct Prereq)
    // 2. Line is between two Ancestors
    if ((line.toId === activeUnitId.value && ancestorIds.value.has(line.fromId)) || (ancestorIds.value.has(line.fromId) && ancestorIds.value.has(line.toId))) {
        return '#f59e0b'; // Amber 500 (Prerequisite color)
    }

    // Line part of Descendant chain (Outgoing from active or internal to descendant chain)
    // 1. Line starts from Active (Direct Dependent)
    // 2. Line is between two Descendants
    if ((line.fromId === activeUnitId.value && descendantIds.value.has(line.toId)) || (descendantIds.value.has(line.fromId) && descendantIds.value.has(line.toId))) {
        return '#10b981'; // Emerald 500 (Unlocks color)
    }

    return '#e2e8f0'; // Slate 200 (faded)
};

const getLineOpacity = (line: (typeof lines.value)[0]) => {
    if (activeUnitId.value === null) return 0.4;

    const isRelated =
        (line.toId === activeUnitId.value && ancestorIds.value.has(line.fromId)) ||
        (ancestorIds.value.has(line.fromId) && ancestorIds.value.has(line.toId)) ||
        (line.fromId === activeUnitId.value && descendantIds.value.has(line.toId)) ||
        (descendantIds.value.has(line.fromId) && descendantIds.value.has(line.toId));

    return isRelated ? 1 : 0.05;
};

// --- Lifecycle ---

let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
    nextTick(() => {
        updateLines();
    });

    if (containerRef.value) {
        resizeObserver = new ResizeObserver(() => {
            updateLines();
        });
        resizeObserver.observe(containerRef.value);
    }

    window.addEventListener('resize', updateLines);
});

onUnmounted(() => {
    if (resizeObserver) resizeObserver.disconnect();
    window.removeEventListener('resize', updateLines);
});
</script>

<template>
    <Head title="Curriculum Roadmap" />

    <CurriculumVersionSummaryLayout :curriculum-version="curriculumVersion">
        <div class="flex h-full flex-col space-y-4 p-8">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="flex items-center space-x-2">
                        <!-- <Link :href="route('curriculum-versions.summary', curriculumVersion.id)">
                            <Button variant="ghost" size="icon">
                                <MoveLeft class="w-5 h-5" />
                            </Button>
                        </Link> -->
                        <h2 class="text-2xl font-bold tracking-tight">Curriculum Roadmap</h2>
                    </div>
                    <p class="text-muted-foreground pl-11">{{ curriculumVersion.program.name }} - {{ curriculumVersion?.specialization?.name }} (Ver. {{ curriculumVersion.version_code }})</p>
                </div>

                <div class="flex items-center space-x-6 text-sm">
                    <div class="flex items-center">
                        <div class="mr-2 h-3 w-3 rounded-full bg-blue-600"></div>
                        <span class="font-medium text-slate-700">Selected Unit</span>
                    </div>
                    <div class="flex items-center">
                        <div class="mr-2 h-3 w-3 rounded-full bg-amber-500"></div>
                        <span class="font-medium text-slate-700">Prerequisite (Must take before)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="mr-2 h-3 w-3 rounded-full bg-emerald-500"></div>
                        <span class="font-medium text-slate-700">Unlocks (Can take after)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="mr-2 h-0.5 w-6 border-t border-dashed border-slate-400 bg-slate-400" style="height: 1px; background: none; border-top-width: 2px"></span>
                        <span class="text-muted-foreground">Co-requisite (Can take with)</span>
                    </div>
                </div>
            </div>

            <!-- Roadmap Container -->
            <div ref="containerRef" class="custom-scrollbar relative flex-1 overflow-auto rounded-xl border bg-slate-50/50">
                <div class="relative inline-flex min-h-full space-x-12 p-8">
                    <!-- SVG Layer -->
                    <!-- We make SVG absolute covering the entire scrollable content area -->
                    <!-- Note: The width/height must match the scrollable content size -->
                    <svg class="pointer-events-none absolute top-0 left-0 z-0 h-full w-full">
                        <defs>
                            <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                                <polygon points="0 0, 10 3.5, 0 7" fill="#94a3b8" />
                            </marker>
                            <marker id="arrowhead-active-ancestor" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                                <polygon points="0 0, 10 3.5, 0 7" fill="#f59e0b" />
                            </marker>
                            <marker id="arrowhead-active-descendant" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                                <polygon points="0 0, 10 3.5, 0 7" fill="#10b981" />
                            </marker>
                        </defs>
                        <g v-for="line in lines" :key="line.id">
                            <!-- Bezier Curve -->
                            <path
                                :d="`M ${line.x1} ${line.y1} C ${line.x1 + 50} ${line.y1}, ${line.x2 - 50} ${line.y2}, ${line.x2} ${line.y2}`"
                                :stroke="getLineColor(line)"
                                :stroke-width="getLineOpacity(line) === 1 ? 2.5 : 1.5"
                                :stroke-opacity="getLineOpacity(line)"
                                fill="none"
                                :stroke-dasharray="line.type === 'co_requisite' ? '5,5' : '0'"
                                :marker-end="getLineOpacity(line) !== 1 ? 'url(#arrowhead)' : getLineColor(line) === '#f59e0b' ? 'url(#arrowhead-active-ancestor)' : 'url(#arrowhead-active-descendant)'"
                            />
                        </g>
                    </svg>

                    <!-- Columns -->
                    <div v-for="sem in semesters" :key="sem.number" class="z-10 flex w-72 flex-shrink-0 flex-col space-y-6">
                        <div class="sticky top-0 z-20 border-b bg-slate-50/95 py-2 text-center font-semibold text-slate-700 backdrop-blur">Semester {{ sem.number }}</div>

                        <div class="flex flex-col space-y-4 pb-8">
                            <template v-for="cu in sem.units" :key="cu.id">
                                <div v-if="cu.unit" :ref="(el) => setUnitRef(el, cu.unit_id!)" @click="handleUnitClick(cu.unit_id!)" class="relative transform cursor-pointer transition-all duration-300" :class="getCardStyle(cu.unit_id!)">
                                    <div
                                        v-if="activeUnitId !== null && getUnitState(cu.unit_id!) === 'ancestor'"
                                        class="absolute -top-3 -right-2 z-30 rounded border border-amber-600 bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white uppercase shadow-sm"
                                    >
                                        Prerequisite
                                    </div>
                                    <div
                                        v-if="activeUnitId !== null && getUnitState(cu.unit_id!) === 'descendant'"
                                        class="absolute -top-3 -right-2 z-30 rounded border border-emerald-600 bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-white uppercase shadow-sm"
                                    >
                                        Unlocks
                                    </div>

                                    <Card class="border-0 bg-transparent transition-shadow hover:shadow-md">
                                        <CardHeader class="p-4 pb-2">
                                            <div class="flex items-start justify-between">
                                                <Badge variant="outline" class="mb-1 bg-white/50 font-mono text-[10px]">{{ cu.unit.code }}</Badge>
                                                <span class="text-muted-foreground text-[10px]">{{ cu.unit.credit_points }} CP</span>
                                            </div>
                                            <CardTitle class="line-clamp-2 text-sm leading-tight font-medium">
                                                {{ cu.unit.name }}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent class="p-4 pt-1">
                                            <!-- Prerequisite indicators (dots) could go here -->
                                            <div v-if="cu.unit.prerequisite_groups.length > 0" class="mt-2 flex gap-1">
                                                <div class="h-1.5 w-1.5 rounded-full bg-slate-300" :class="{ 'bg-amber-400': getUnitState(cu.unit_id!) !== null }" title="Has Prerequisites"></div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CurriculumVersionSummaryLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}
</style>
