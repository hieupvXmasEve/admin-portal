<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Guardian } from '@/types/application-guardian';
import { router } from '@inertiajs/vue3';
import { Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    applicationId: number;
    guardians: Guardian[];
    guardianRelationships: string[];
    editable: boolean;
}

const props = defineProps<Props>();

const isSubmitting = ref(false);
const showGuardianDialog = ref(false);
const editingGuardianId = ref<number | null>(null);

const guardianForm = reactive({
    full_name: '',
    relationship: '' as string,
    phone: '',
    email: '',
    occupation: '',
    address: '',
    is_primary: false,
});

const guardianDialogTitle = computed(() => (editingGuardianId.value === null ? 'Add guardian' : 'Edit guardian'));

const resetGuardianForm = () => {
    guardianForm.full_name = '';
    guardianForm.relationship = '';
    guardianForm.phone = '';
    guardianForm.email = '';
    guardianForm.occupation = '';
    guardianForm.address = '';
    // The first guardian is always primary; offer it pre-checked when none exist.
    guardianForm.is_primary = props.guardians.length === 0;
};

const openAddGuardian = () => {
    editingGuardianId.value = null;
    resetGuardianForm();
    showGuardianDialog.value = true;
};

const openEditGuardian = (guardian: Guardian) => {
    editingGuardianId.value = guardian.id;
    guardianForm.full_name = guardian.full_name;
    guardianForm.relationship = guardian.relationship ?? '';
    guardianForm.phone = guardian.phone ?? '';
    guardianForm.email = guardian.email ?? '';
    guardianForm.occupation = guardian.occupation ?? '';
    guardianForm.address = guardian.address ?? '';
    guardianForm.is_primary = guardian.is_primary;
    showGuardianDialog.value = true;
};

const submitGuardian = () => {
    if (!guardianForm.full_name.trim()) {
        toast.error('A guardian name is required.');
        return;
    }

    const payload = {
        full_name: guardianForm.full_name,
        relationship: guardianForm.relationship || null,
        phone: guardianForm.phone || null,
        email: guardianForm.email || null,
        occupation: guardianForm.occupation || null,
        address: guardianForm.address || null,
        is_primary: guardianForm.is_primary,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(editingGuardianId.value === null ? 'Guardian added.' : 'Guardian updated.');
            showGuardianDialog.value = false;
        },
        onError: () => toast.error('Could not save the guardian. Check the fields and try again.'),
        onFinish: () => {
            isSubmitting.value = false;
        },
    };

    isSubmitting.value = true;

    if (editingGuardianId.value === null) {
        router.post(route('student-applications.guardians.store', props.applicationId), payload, options);
    } else {
        router.put(route('student-applications.guardians.update', [props.applicationId, editingGuardianId.value]), payload, options);
    }
};

const makePrimary = (guardian: Guardian) => {
    isSubmitting.value = true;
    router.put(
        route('student-applications.guardians.update', [props.applicationId, guardian.id]),
        { full_name: guardian.full_name, is_primary: true },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Primary guardian updated.'),
            onError: () => toast.error('Could not set the primary guardian.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const removeGuardian = (guardian: Guardian) => {
    if (!window.confirm(`Remove ${guardian.full_name} as a guardian?`)) {
        return;
    }
    isSubmitting.value = true;
    router.delete(route('student-applications.guardians.destroy', [props.applicationId, guardian.id]), {
        preserveScroll: true,
        onSuccess: () => toast.success('Guardian removed.'),
        onError: () => toast.error('Could not remove the guardian.'),
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const relationshipLabel = (value: string | null): string => {
    if (!value) {
        return 'Guardian';
    }
    return value.charAt(0).toUpperCase() + value.slice(1);
};
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-4 space-y-0">
            <div>
                <CardTitle>Guardians</CardTitle>
                <CardDescription>Parents and responsible adults. Exactly one is primary.</CardDescription>
            </div>
            <Button v-if="editable" size="sm" variant="outline" :disabled="isSubmitting" @click="openAddGuardian">
                <Plus class="mr-2 h-4 w-4" />
                Add guardian
            </Button>
        </CardHeader>
        <CardContent class="space-y-3">
            <p v-if="guardians.length === 0" class="text-muted-foreground text-sm">No guardians recorded yet.</p>

            <div
                v-for="guardian in guardians"
                :key="guardian.id"
                class="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-4"
                :class="guardian.is_primary ? 'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-900/10' : ''"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold">{{ guardian.full_name }}</p>
                        <Badge v-if="guardian.is_primary" class="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"> Primary </Badge>
                        <Badge variant="secondary">{{ relationshipLabel(guardian.relationship) }}</Badge>
                    </div>
                    <div class="text-muted-foreground mt-1 space-y-0.5 text-sm">
                        <p v-if="guardian.phone">📞 {{ guardian.phone }}</p>
                        <p v-if="guardian.email">✉️ {{ guardian.email }}</p>
                        <p v-if="guardian.occupation">💼 {{ guardian.occupation }}</p>
                        <p v-if="guardian.address">🏠 {{ guardian.address }}</p>
                    </div>
                </div>

                <div v-if="editable" class="flex items-center gap-1">
                    <Button v-if="!guardian.is_primary" size="sm" variant="ghost" :disabled="isSubmitting" title="Set as primary" @click="makePrimary(guardian)">
                        <Star class="h-4 w-4" />
                    </Button>
                    <Button size="sm" variant="ghost" :disabled="isSubmitting" title="Edit" @click="openEditGuardian(guardian)">
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button size="sm" variant="ghost" class="text-rose-600 hover:text-rose-700" :disabled="isSubmitting" title="Remove" @click="removeGuardian(guardian)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Guardian add/edit dialog -->
    <Dialog v-model:open="showGuardianDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ guardianDialogTitle }}</DialogTitle>
                <DialogDescription>Record a parent or responsible adult for this application.</DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-4 py-2 sm:grid-cols-2">
                <div class="space-y-2 sm:col-span-2">
                    <Label for="guardian-name">Full name</Label>
                    <Input id="guardian-name" v-model="guardianForm.full_name" placeholder="Full name" />
                </div>

                <div class="space-y-2">
                    <Label for="guardian-relationship">Relationship</Label>
                    <Select v-model="guardianForm.relationship">
                        <SelectTrigger id="guardian-relationship">
                            <SelectValue placeholder="Select relationship" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in guardianRelationships" :key="option" :value="option">
                                {{ relationshipLabel(option) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="guardian-phone">Phone</Label>
                    <Input id="guardian-phone" v-model="guardianForm.phone" placeholder="Phone" />
                </div>

                <div class="space-y-2">
                    <Label for="guardian-email">Email</Label>
                    <Input id="guardian-email" v-model="guardianForm.email" type="email" placeholder="Email" />
                </div>

                <div class="space-y-2">
                    <Label for="guardian-occupation">Occupation</Label>
                    <Input id="guardian-occupation" v-model="guardianForm.occupation" placeholder="Occupation" />
                </div>

                <div class="space-y-2 sm:col-span-2">
                    <Label for="guardian-address">Address</Label>
                    <Input id="guardian-address" v-model="guardianForm.address" placeholder="Address" />
                </div>

                <label class="flex items-center gap-2 sm:col-span-2">
                    <Checkbox v-model="guardianForm.is_primary" />
                    <span class="text-sm">Primary guardian (emergency contact &amp; parent account)</span>
                </label>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showGuardianDialog = false">Cancel</Button>
                <Button :disabled="isSubmitting" @click="submitGuardian">Save guardian</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
