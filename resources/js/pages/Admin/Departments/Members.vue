<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { UserPlus, Shield, User as UserIcon, Trash2, CheckCircle2, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Member {
    id: number;
    user_id: number;
    department_role: 'head' | 'staff';
    is_active: boolean;
    user: User;
}

interface Department {
    id: number;
    name: string;
    code: string;
}

interface Props {
    department: Department;
    members: Member[];
    availableUsers: User[];
}

const props = defineProps<Props>();

const addMemberDialog = ref(false);

const addMemberForm = useForm({
    user_id: '',
    department_role: 'staff' as 'head' | 'staff',
});

const onAddMember = () => {
    addMemberForm.post(route('admin.departments.members.store', props.department.id), {
        onSuccess: () => {
            addMemberDialog.value = false;
            addMemberForm.reset();
        }
    });
};

const onUpdateMember = (member: Member, data: Partial<Member>) => {
    router.put(route('admin.departments.members.update', [props.department.id, member.id]), {
        department_role: data.department_role ?? member.department_role,
        is_active: data.is_active ?? member.is_active,
    }, { preserveScroll: true });
};

const onRemoveMember = (member: Member) => {
    if (confirm('Are you sure you want to remove this member?')) {
        router.delete(route('admin.departments.members.destroy', [props.department.id, member.id]), {
            preserveScroll: true
        });
    }
};
</script>

<template>
    <Head :title="`${department.name} Members`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Department Members</h1>
                <p class="text-muted-foreground text-sm mt-1">
                    Manage heads and staff for <span class="font-bold text-foreground">{{ department.name }} ({{ department.code }})</span>
                </p>
            </div>

            <Dialog v-model:open="addMemberDialog">
                <DialogTrigger as-child>
                    <Button>
                        <UserPlus class="h-4 w-4 mr-2" />
                        Add Member
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Department Member</DialogTitle>
                        <DialogDescription>Assign a user to this department with a specific role.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label for="user">User</Label>
                            <Select v-model="addMemberForm.user_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a user" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="user in availableUsers" :key="user.id" :value="user.id.toString()">
                                        {{ user.name }} ({{ user.email }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="addMemberForm.errors.user_id" class="text-xs text-destructive">{{ addMemberForm.errors.user_id }}</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="role">Role</Label>
                            <Select v-model="addMemberForm.department_role">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="staff">Staff</SelectItem>
                                    <SelectItem value="head">Department Head</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="addMemberForm.errors.department_role" class="text-xs text-destructive">{{ addMemberForm.errors.department_role }}</p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="addMemberDialog = false">Cancel</Button>
                        <Button @click="onAddMember" :disabled="addMemberForm.processing">Add Member</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Members List</CardTitle>
                <CardDescription>All users associated with this department.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>User</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="member in members" :key="member.id">
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                                        {{ member.user.name[0] }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-sm">{{ member.user.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ member.user.email }}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Select 
                                    :model-value="member.department_role" 
                                    @update:model-value="(val: any) => onUpdateMember(member, { department_role: val })"
                                >
                                    <SelectTrigger class="w-[140px] h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="staff">
                                            <div class="flex items-center">
                                                <UserIcon class="h-3 w-3 mr-2" /> Staff
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="head">
                                            <div class="flex items-center text-primary font-bold">
                                                <Shield class="h-3 w-3 mr-2" /> Head
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Switch 
                                        :model-value="member.is_active" 
                                        @update:model-value="(val: any) => onUpdateMember(member, { is_active: val })" 
                                    />
                                    <Badge :variant="member.is_active ? 'default' : 'secondary'" class="text-[10px] px-1.5 py-0">
                                        {{ member.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive hover:bg-destructive/10" @click="onRemoveMember(member)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!members.length">
                            <TableCell colspan="4" class="h-24 text-center text-muted-foreground italic">
                                No members assigned to this department yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
