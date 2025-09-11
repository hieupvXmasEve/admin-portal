<script setup lang="ts">
interface SemesterInfo {
    id: number;
    code: string;
    name: string;
    start_date: string | null;
    end_date: string | null;
    enrollment_start_date: string | null;
    enrollment_end_date: string | null;
    is_registration_open: boolean;
}

const props = defineProps<{
    semester: SemesterInfo | null;
}>();
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-foreground text-lg font-semibold">Current Semester</h2>

        <div class="bg-card rounded-xl border p-6">
            <div v-if="props.semester" class="space-y-3">
                <div>
                    <div class="text-foreground text-xl font-semibold">
                        {{ props.semester.name }}
                    </div>
                    <div class="text-muted-foreground text-sm">
                        {{ props.semester.code }}
                    </div>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Period:</span>
                        <span class="font-medium"> {{ props.semester.start_date }} - {{ props.semester.end_date }} </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Enrollment Period:</span>
                        <span class="font-medium"> {{ props.semester.enrollment_start_date }} - {{ props.semester.enrollment_end_date }} </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Registration Status:</span>
                        <span :class="props.semester.is_registration_open ? 'font-medium text-green-600' : 'font-medium text-amber-600'">
                            {{ props.semester.is_registration_open ? 'Open' : 'Closed' }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="py-8 text-center">
                <div class="text-muted-foreground">No active semester</div>
                <div class="text-muted-foreground mt-1 text-sm">Please contact your administrator to set up the current semester.</div>
            </div>
        </div>
    </div>
</template>
