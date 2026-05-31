<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Mail, Save } from 'lucide-vue-next';
import { route } from 'ziggy-js';

const props = defineProps<{
    settings: {
        attendance_warning_ratio: number;
        channels: string[];
        academic_warning_title: string;
        academic_warning_body: string;
        attendance_warning_title: string;
        attendance_warning_body: string;
        attendance_exceeded_title: string;
        attendance_exceeded_body: string;
    };
}>();

const form = useForm({
    attendance_warning_ratio: props.settings.attendance_warning_ratio,
    channels: [...props.settings.channels],
    academic_warning_title: props.settings.academic_warning_title,
    academic_warning_body: props.settings.academic_warning_body,
    attendance_warning_title: props.settings.attendance_warning_title,
    attendance_warning_body: props.settings.attendance_warning_body,
    attendance_exceeded_title: props.settings.attendance_exceeded_title,
    attendance_exceeded_body: props.settings.attendance_exceeded_body,
});

const hasChannel = (channel: string) => form.channels.includes(channel);

const toggleChannel = (channel: string, checked: boolean) => {
    if (checked && !form.channels.includes(channel)) {
        form.channels.push(channel);
    }

    if (!checked) {
        form.channels = form.channels.filter((item) => item !== channel);
    }
};

const onChannelChange = (channel: string, event: Event) => {
    toggleChannel(channel, (event.target as HTMLInputElement).checked);
};

const submit = () => {
    form.put(route('academic.warnings.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Warning Settings" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Warning Settings</h1>
                <p class="text-sm text-muted-foreground">Message templates and attendance threshold behavior.</p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="route('academic.warnings.index')">
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </Link>
            </Button>
        </div>

        <Alert>
            <Bell class="h-4 w-4" />
            <AlertTitle>Delivery channels</AlertTitle>
            <AlertDescription>Keep both channels enabled for student-facing warnings.</AlertDescription>
        </Alert>

        <form class="space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Thresholds</CardTitle>
                    <CardDescription>Attendance warnings trigger at this ratio of the maximum allowed absences.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="attendance_warning_ratio">Warning ratio</Label>
                        <Input
                            id="attendance_warning_ratio"
                            v-model.number="form.attendance_warning_ratio"
                            type="number"
                            min="0.1"
                            max="1"
                            step="0.05"
                        />
                        <p v-if="form.errors.attendance_warning_ratio" class="text-sm text-destructive">{{ form.errors.attendance_warning_ratio }}</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-md border p-3">
                        <input
                            type="checkbox"
                            class="h-4 w-4"
                            :checked="hasChannel('realtime')"
                            @change="onChannelChange('realtime', $event)"
                        />
                        <span class="inline-flex items-center gap-2 text-sm font-medium"><Bell class="h-4 w-4" /> In-app</span>
                    </label>

                    <label class="flex items-center gap-3 rounded-md border p-3">
                        <input
                            type="checkbox"
                            class="h-4 w-4"
                            :checked="hasChannel('email')"
                            @change="onChannelChange('email', $event)"
                        />
                        <span class="inline-flex items-center gap-2 text-sm font-medium"><Mail class="h-4 w-4" /> Email</span>
                    </label>
                    <p v-if="form.errors.channels" class="text-sm text-destructive md:col-span-3">{{ form.errors.channels }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Academic Standing</CardTitle>
                    <CardDescription>Used when cumulative GPA is below 50.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="academic_warning_title">Title</Label>
                        <Input id="academic_warning_title" v-model="form.academic_warning_title" />
                        <p v-if="form.errors.academic_warning_title" class="text-sm text-destructive">{{ form.errors.academic_warning_title }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="academic_warning_body">Message</Label>
                        <Textarea id="academic_warning_body" v-model="form.academic_warning_body" rows="5" />
                        <p v-if="form.errors.academic_warning_body" class="text-sm text-destructive">{{ form.errors.academic_warning_body }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Attendance Warning</CardTitle>
                    <CardDescription>Used after the early warning threshold is crossed.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="attendance_warning_title">Title</Label>
                        <Input id="attendance_warning_title" v-model="form.attendance_warning_title" />
                        <p v-if="form.errors.attendance_warning_title" class="text-sm text-destructive">{{ form.errors.attendance_warning_title }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="attendance_warning_body">Message</Label>
                        <Textarea id="attendance_warning_body" v-model="form.attendance_warning_body" rows="5" />
                        <p v-if="form.errors.attendance_warning_body" class="text-sm text-destructive">{{ form.errors.attendance_warning_body }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Attendance Limit Exceeded</CardTitle>
                    <CardDescription>Used after absences exceed the allowed absence limit.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="attendance_exceeded_title">Title</Label>
                        <Input id="attendance_exceeded_title" v-model="form.attendance_exceeded_title" />
                        <p v-if="form.errors.attendance_exceeded_title" class="text-sm text-destructive">{{ form.errors.attendance_exceeded_title }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="attendance_exceeded_body">Message</Label>
                        <Textarea id="attendance_exceeded_body" v-model="form.attendance_exceeded_body" rows="5" />
                        <p v-if="form.errors.attendance_exceeded_body" class="text-sm text-destructive">{{ form.errors.attendance_exceeded_body }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ form.processing ? 'Saving' : 'Save settings' }}
                </Button>
            </div>
        </form>
    </div>
</template>
