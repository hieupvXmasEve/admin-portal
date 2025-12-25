<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Users, Settings, Building } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Department {
    id: number;
    name: string;
    code: string;
    memberships_count?: number;
}

interface Props {
    departments: Department[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Departments" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Departments</h1>
                <p class="text-muted-foreground text-sm mt-1">Manage university departments and their assigned staff/heads.</p>
            </div>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Department List</CardTitle>
                <CardDescription>All academic and administrative departments.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Members</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="dept in departments" :key="dept.id">
                            <TableCell class="font-mono text-xs font-bold">{{ dept.code }}</TableCell>
                            <TableCell class="font-medium text-sm">{{ dept.name }}</TableCell>
                            <TableCell>
                                <Badge variant="secondary" class="flex items-center gap-1 w-fit">
                                    <Users class="h-3 w-3" />
                                    {{ dept.memberships_count || 0 }} Members
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Link :href="route('admin.departments.members.index', dept.id)">
                                    <Button variant="ghost" size="sm">
                                        <Users class="h-4 w-4 mr-2" />
                                        Manage Members
                                    </Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!departments.length">
                            <TableCell colspan="4" class="h-24 text-center text-muted-foreground italic">
                                No departments found.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
