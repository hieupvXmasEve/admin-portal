# Phase 03: Frontend Campus-Aware UI

**Status:** completed
**Effort:** 1h
**Dependencies:** Phase 02

---

## Objective

Update `Send.vue` to:
1. Display current campus context
2. Include `campus_id` in form submission
3. Pass `campus_id` to search API

---

## Task 3.1: Update Props Interface

**File:** `resources/js/pages/Admin/Notifications/Send.vue`

```diff
interface Props {
    categories: Array<{ value: string; label: string; description: string }>;
    programs: Program[];
+   currentCampusId: number | null;
}
```

---

## Task 3.2: Update Search API Call

Add `campus_id` to the target search request:

```diff
const searchTargets = async () => {
    if (!search.value && programId.value === 'all' && notifiableType.value === 'student') {
        recipients.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const { data: apiData } = await api.get<Recipient[]>(route(NOTIFICATION_ROUTE_NAMES.SEARCH_TARGETS), {
            search: search.value,
            type: notifiableType.value,
-           program_id: notifiableType.value === 'student' && programId.value !== 'all' ? programId.value : null
+           program_id: notifiableType.value === 'student' && programId.value !== 'all' ? programId.value : null,
+           campus_id: props.currentCampusId,
        });

        if (apiData.value?.success && apiData.value?.data) {
            recipients.value = apiData.value.data.filter(
                (r: Recipient) => !selectedRecipients.value.some(sel => sel.id === r.id)
            );
        }
    } catch (error) {
        toast.error('Failed to search recipients');
    } finally {
        isSearching.value = false;
    }
};
```

---

## Task 3.3: Update Form Submission

Include `campus_id` in the payload:

```diff
const onSubmit = handleSubmit(async (values) => {
    if (selectedRecipients.value.length === 0) {
        toast.error('Please select at least one recipient');
        return;
    }

    const payload = {
        ...values,
        notifiable_type: notifiableType.value,
-       notifiable_ids: selectedRecipients.value.map(r => r.id)
+       notifiable_ids: selectedRecipients.value.map(r => r.id),
+       campus_id: props.currentCampusId,
    };

    try {
        const { data: apiData } = await api.post(route(NOTIFICATION_ROUTE_NAMES.STORE), payload);

        if (apiData.value?.success) {
-           toast.success(`Notification sent to ${selectedRecipients.value.length} recipients`);
+           toast.success(`Notification queued for ${selectedRecipients.value.length} recipients`);
            resetForm();
            selectedRecipients.value = [];
            router.visit(route(NOTIFICATION_ROUTE_NAMES.INDEX));
        } else {
            toast.error(apiData.value?.message || 'Failed to send notification');
        }
    } catch (e: any) {
        toast.error('Failed to send notification');
    }
});
```

---

## Task 3.4: Add Campus Context Display (Optional Enhancement)

Add a visual indicator showing which campus the notification is being sent to:

```vue
<!-- Add after the page description -->
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold tracking-tight">Send Notification</h1>
        <p class="text-muted-foreground text-sm">
            Send a manual notification to selected recipients via Realtime.
        </p>
    </div>
    <div class="flex items-center gap-4">
        <div v-if="currentCampusId" class="flex items-center gap-2 text-sm text-muted-foreground">
            <Building2 class="h-4 w-4" />
            <span>Campus ID: {{ currentCampusId }}</span>
        </div>
        <Button variant="outline" @click="router.visit(route(NOTIFICATION_ROUTE_NAMES.INDEX))">
            <Clock class="mr-2 h-4 w-4" />
            View History
        </Button>
    </div>
</div>
```

**Note:** This is optional but improves UX by showing users which campus context they're operating in. The campus name could be passed as a prop instead of just the ID for better display.

---

## Task 3.5: Update Info Banner Text

```diff
<div class="md:col-span-2 bg-blue-50 border border-blue-100 rounded-md p-3 flex gap-3">
    <Info class="h-5 w-5 text-blue-500 shrink-0" />
    <div class="text-xs text-blue-700 leading-relaxed">
-       This notification will be delivered instantly to the recipient's portal if they are
-       online, and stored in their inbox database for later viewing.
+       This notification will be queued for delivery to the recipient's portal. 
+       Recipients online will receive it instantly; others will see it in their inbox.
+       Only recipients belonging to your current campus will be notified.
    </div>
</div>
```

---

## Complete Updated Script Section

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import {
    Building2,
    Check,
    Clock,
    Info,
    Loader2,
    Search,
    Send,
    User,
    X
} from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import * as z from 'zod';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import { NOTIFICATION_ROUTE_NAMES } from '@/constants/notification-routes';

interface Program {
    id: number;
    name: string;
}

interface Recipient {
    id: number;
    name: string;
    code?: string;
    email: string;
}

interface Props {
    categories: Array<{ value: string; label: string; description: string }>;
    programs: Program[];
    currentCampusId: number | null;
}

const props = defineProps<Props>();

const api = useApi();

// --- Selection State ---
const search = ref('');
const programId = ref('all');
const notifiableType = ref<'student' | 'user' | 'lecturer'>('student');
const recipients = ref<Recipient[]>([]);
const selectedRecipients = ref<Recipient[]>([]);
const isSearching = ref(false);

const recipientTypes = [
    { value: 'student', label: 'Student' },
    { value: 'user', label: 'Staff/User' },
    { value: 'lecturer', label: 'Lecturer' },
];

const searchTargets = async () => {
    if (!search.value && programId.value === 'all' && notifiableType.value === 'student') {
        recipients.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const { data: apiData } = await api.get<Recipient[]>(route(NOTIFICATION_ROUTE_NAMES.SEARCH_TARGETS), {
            search: search.value,
            type: notifiableType.value,
            program_id: notifiableType.value === 'student' && programId.value !== 'all' ? programId.value : null,
            campus_id: props.currentCampusId,
        });

        if (apiData.value?.success && apiData.value?.data) {
            recipients.value = apiData.value.data.filter(
                (r: Recipient) => !selectedRecipients.value.some(sel => sel.id === r.id)
            );
        }
    } catch (error) {
        toast.error('Failed to search recipients');
    } finally {
        isSearching.value = false;
    }
};

// ... rest of component unchanged except onSubmit

const onSubmit = handleSubmit(async (values) => {
    if (selectedRecipients.value.length === 0) {
        toast.error('Please select at least one recipient');
        return;
    }

    const payload = {
        ...values,
        notifiable_type: notifiableType.value,
        notifiable_ids: selectedRecipients.value.map(r => r.id),
        campus_id: props.currentCampusId,
    };

    try {
        const { data: apiData } = await api.post(route(NOTIFICATION_ROUTE_NAMES.STORE), payload);

        if (apiData.value?.success) {
            toast.success(`Notification queued for ${selectedRecipients.value.length} recipients`);
            resetForm();
            selectedRecipients.value = [];
            router.visit(route(NOTIFICATION_ROUTE_NAMES.INDEX));
        } else {
            toast.error(apiData.value?.message || 'Failed to send notification');
        }
    } catch (e: any) {
        toast.error('Failed to send notification');
    }
});
</script>
```

---

## Acceptance Criteria

- [ ] Props interface includes `currentCampusId`
- [ ] Search API call includes `campus_id` parameter
- [ ] Form submission includes `campus_id` in payload
- [ ] Toast message updated to say "queued" instead of "sent"
- [ ] Info banner updated to mention campus isolation
- [ ] TypeScript compiles without errors (`pnpm run type-check`)
- [ ] UI renders correctly with campus context

---

## Files Changed

| File | Action |
|------|--------|
| `resources/js/pages/Admin/Notifications/Send.vue` | Modify |
