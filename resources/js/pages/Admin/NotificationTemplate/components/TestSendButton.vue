<script setup lang="ts">
import { ref } from 'vue';
import { route } from 'ziggy-js';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

interface NotificationTemplate {
    id: number;
    campus_id: number;
    type_key: string;
}

interface Draft {
    subject: string;
    body_html: string;
}

const props = defineProps<{
    template: NotificationTemplate;
    draft: Draft;
    userEmail: string;
}>();

const isOpen = ref<boolean>(false);
const isSending = ref<boolean>(false);

const sendTestEmail = async () => {
    isSending.value = true;

    try {
        const response = await fetch(
            route('api.admin.notification-templates.test-send', { template: props.template.id }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    subject: props.draft.subject,
                    body_html: props.draft.body_html,
                }),
            },
        );

        if (response.status === 429) {
            toast.error('Rate limit: 5 per minute, try again later');
            return;
        }

        if (!response.ok) {
            const json = await response.json().catch(() => ({}));
            toast.error(json?.message ?? 'Failed to send test email');
            return;
        }

        const json = await response.json();
        toast.success(`Test email sent to ${json.data?.sent_to ?? props.userEmail}`);
        isOpen.value = false;
    } catch {
        toast.error('Failed to send test email');
    } finally {
        isSending.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                Send Test Email
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Send test email</DialogTitle>
                <DialogDescription>
                    A test email with sample variable values will be sent to
                    <strong>{{ props.userEmail }}</strong>.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="ghost" @click="isOpen = false" :disabled="isSending">
                    Cancel
                </Button>
                <Button @click="sendTestEmail" :disabled="isSending">
                    {{ isSending ? 'Sending…' : 'Send' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
