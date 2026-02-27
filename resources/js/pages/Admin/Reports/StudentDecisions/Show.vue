<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { StudentActionLog } from '@/types/student-action';
import type { StudentDecision } from '@/types/student-decision';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    decision: StudentDecision;
    linkedActions: {
        data: StudentActionLog[];
        links: any;
        meta: any;
    };
    filters: {
        linked_per_page: number;
    };
}

const props = defineProps<Props>();

const linkedPerPage = ref(props.filters.linked_per_page ?? 10);

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN');
};

const reloadWithPerPage = () => {
    router.get(
        studentRoutes.studentDecisionsShow(props.decision.id),
        { linked_per_page: linkedPerPage.value },
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="`Decision ${decision.decision_number}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Decision Detail</h1>
                <p class="text-muted-foreground mt-1">{{ decision.decision_number }} - {{ decision.decision_name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="studentRoutes.studentDecisionsIndex()">
                    <Button variant="outline">Back to List</Button>
                </Link>
                <a v-if="decision.upload_record?.url" :href="decision.upload_record.url" target="_blank" rel="noopener noreferrer">
                    <Button variant="outline">
                        <ExternalLink class="mr-2 h-4 w-4" />
                        View File
                    </Button>
                </a>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Decision Information</CardTitle>
                <CardDescription>Metadata and linked statistics.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-sm">Signer</p>
                        <p class="font-medium">{{ decision.decision_signer }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Issued</p>
                        <p class="font-medium">{{ formatDateOnly(decision.issued_at) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Expires</p>
                        <p class="font-medium">{{ formatDateOnly(decision.expires_at) }}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <Badge variant="outline">Linked Actions: {{ decision.total_linked_actions ?? 0 }}</Badge>
                    <Badge variant="outline">Linked Students: {{ decision.total_linked_students ?? 0 }}</Badge>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Linked Student Actions</CardTitle>
                        <CardDescription>Open action detail from each row.</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground text-sm">Per page</span>
                        <select v-model.number="linkedPerPage" class="rounded border px-2 py-1 text-sm" @change="reloadWithPerPage">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="py-2">Student</th>
                                <th class="py-2">Action Type</th>
                                <th class="py-2">Changed At</th>
                                <th class="py-2">Reason</th>
                                <th class="py-2">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in linkedActions.data" :key="row.id" class="border-b">
                                <td class="py-2">{{ row.student?.student_id }} - {{ row.student?.full_name }}</td>
                                <td class="py-2">{{ row.action_type }}</td>
                                <td class="py-2">{{ formatDateOnly(row.created_at) }}</td>
                                <td class="py-2">{{ row.reason }}</td>
                                <td class="py-2">
                                    <Link :href="studentRoutes.studentStatusActionShow(row.id)">
                                        <Button size="sm" variant="outline">View Action</Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Link
                        v-for="link in linkedActions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded border px-2 py-1 text-sm',
                            link.active ? 'bg-primary text-primary-foreground' : 'bg-background',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
