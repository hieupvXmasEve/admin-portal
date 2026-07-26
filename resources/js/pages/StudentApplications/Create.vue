<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface CodeOption {
    id: number;
    code: string;
    name: string;
}

interface Props {
    campuses: CodeOption[];
    programs: CodeOption[];
    semesters: CodeOption[];
}

defineProps<Props>();

const form = useForm({
    full_name: '',
    student_code: '',
    email: '',
    phone: '',
    gender: '' as '' | 'male' | 'female' | 'other',
    national_id: '',
    campus_code: '',
    intended_program: '',
    intake: '',
    address: '',
});

const goBack = () => window.history.back();

const submit = () => {
    form.transform((data) => ({
        ...data,
        gender: data.gender || null,
        national_id: data.national_id || null,
        intended_program: data.intended_program || null,
        intake: data.intake || null,
        address: data.address || null,
    })).post(route('student-applications.store'), {
        onSuccess: () => toast.success('Application created.'),
        onError: () => toast.error('Please fix the highlighted fields.'),
    });
};
</script>

<template>
    <Head title="New student application" />

    <div class="mx-auto max-w-3xl">
        <Button variant="ghost" class="mb-4" @click="goBack">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
        </Button>

        <div class="mb-6">
            <h1 class="text-3xl font-bold tracking-tight">New student application</h1>
            <p class="text-muted-foreground mt-1 text-sm">Manual entry. The application starts as <strong>pending</strong> until approved.</p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Applicant</CardTitle>
                    <CardDescription>Identity and the CRM-issued student code</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="full_name">Full name *</Label>
                        <Input id="full_name" v-model="form.full_name" placeholder="Full name" />
                        <p v-if="form.errors.full_name" class="text-destructive text-xs">{{ form.errors.full_name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="student_code">Student code *</Label>
                        <Input id="student_code" v-model="form.student_code" placeholder="e.g. S1234567" />
                        <p v-if="form.errors.student_code" class="text-destructive text-xs">{{ form.errors.student_code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="gender">Gender</Label>
                        <Select v-model="form.gender">
                            <SelectTrigger id="gender">
                                <SelectValue placeholder="Select gender" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="male">Male</SelectItem>
                                <SelectItem value="female">Female</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label for="national_id">National ID</Label>
                        <Input id="national_id" v-model="form.national_id" placeholder="National ID" />
                        <p v-if="form.errors.national_id" class="text-destructive text-xs">{{ form.errors.national_id }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Contact</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="email">Email *</Label>
                        <Input id="email" v-model="form.email" type="email" placeholder="Email" />
                        <p v-if="form.errors.email" class="text-destructive text-xs">{{ form.errors.email }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" placeholder="Phone" />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label for="address">Address</Label>
                        <Textarea id="address" v-model="form.address" rows="2" placeholder="Address" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Admission intent</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="campus_code">Campus *</Label>
                        <Select v-model="form.campus_code">
                            <SelectTrigger id="campus_code">
                                <SelectValue placeholder="Select campus" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="campus in campuses" :key="campus.code" :value="campus.code"> {{ campus.name }} ({{ campus.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.campus_code" class="text-destructive text-xs">{{ form.errors.campus_code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="intended_program">Intended program *</Label>
                        <Select v-model="form.intended_program">
                            <SelectTrigger id="intended_program">
                                <SelectValue placeholder="Select program" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="program in programs" :key="program.code" :value="program.code"> {{ program.name }} ({{ program.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.intended_program" class="text-destructive text-xs">{{ form.errors.intended_program }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="intake">Intake *</Label>
                        <Select v-model="form.intake">
                            <SelectTrigger id="intake">
                                <SelectValue placeholder="Select intake" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.code" :value="semester.code"> {{ semester.name }} ({{ semester.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.intake" class="text-destructive text-xs">{{ form.errors.intake }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="goBack">Cancel</Button>
                <Button type="submit" :disabled="form.processing">Create application</Button>
            </div>
        </form>
    </div>
</template>
