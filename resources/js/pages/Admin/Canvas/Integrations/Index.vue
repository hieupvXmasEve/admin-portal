<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { Head, router } from '@inertiajs/vue3';
import { Plus, ExternalLink, RefreshCw, Power, Trash2, CheckCircle2, XCircle, Clock, AlertCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface CanvasIntegration {
    id: number;
    canvas_url: string;
    client_id: string;
    has_token: boolean;
    has_refresh_token: boolean;
    token_expires_at: string | null;
    is_active: boolean;
    sync_status: 'idle' | 'syncing' | 'completed' | 'failed';
    last_sync_at: string | null;
    sync_error: string | null;
    created_at: string;
    created_by: {
        id: number;
        name: string;
    } | null;
}

const isTokenExpired = (integration: CanvasIntegration): boolean => {
    if (!integration.token_expires_at) return true;
    return new Date(integration.token_expires_at) < new Date();
};

const canAutoRefresh = (integration: CanvasIntegration): boolean => {
    return integration.has_refresh_token;
};

const needsManualAuth = (integration: CanvasIntegration): boolean => {
    // Only need manual auth if no token AND no refresh token
    return !integration.has_token || (!canAutoRefresh(integration) && isTokenExpired(integration));
};

interface Props {
    integrations: CanvasIntegration[];
}

const props = defineProps<Props>();
const { showConfirmDialog } = useGlobalConfirmDialog();

const showCreateDialog = ref(false);
const isSubmitting = ref(false);

const form = ref({
    canvas_url: '',
    client_id: '',
    client_secret: '',
});

const createIntegration = () => {
    isSubmitting.value = true;
    
    router.post('/admin/canvas/integrations', form.value, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Canvas integration created successfully');
            showCreateDialog.value = false;
            form.value = {
                canvas_url: '',
                client_id: '',
                client_secret: '',
            };
        },
        onError: (errors) => {
            toast.error('Failed to create integration');
            console.error(errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const authorizeIntegration = (integration: CanvasIntegration) => {
    window.location.href = `/admin/canvas/oauth/redirect/${integration.id}`;
};

const syncCourses = (integration: CanvasIntegration) => {
    if (needsManualAuth(integration)) {
        toast.error('Please authorize the integration first');
        return;
    }

    if (integration.sync_status === 'syncing') {
        toast.warning('Sync already in progress');
        return;
    }

    router.post(`/admin/canvas/sync/${integration.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Course sync started');
        },
        onError: () => {
            toast.error('Failed to start sync');
        },
    });
};

const toggleActive = (integration: CanvasIntegration) => {
    const action = integration.is_active ? 'deactivate' : 'activate';
    
    showConfirmDialog({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Integration`,
        message: `Are you sure you want to ${action} this Canvas integration?`,
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
    }, {
        onConfirm: () => {
            router.post(`/admin/canvas/integrations/${integration.id}/toggle`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Integration ${action}d successfully`);
                },
                onError: () => {
                    toast.error(`Failed to ${action} integration`);
                },
            });
        },
    });
};

const deleteIntegration = (integration: CanvasIntegration) => {
    showConfirmDialog({
        title: 'Delete Integration',
        message: 'Are you sure you want to delete this Canvas integration? This action cannot be undone.',
        confirmText: 'Delete',
    }, {
        onConfirm: () => {
            router.delete(`/admin/canvas/integrations/${integration.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Integration deleted successfully');
                },
                onError: () => {
                    toast.error('Failed to delete integration');
                },
            });
        },
    });
};

const revokeAuthorization = (integration: CanvasIntegration) => {
    showConfirmDialog({
        title: 'Revoke Authorization',
        message: 'Are you sure you want to revoke Canvas authorization? You will need to re-authorize to sync courses.',
        confirmText: 'Revoke',
    }, {
        onConfirm: () => {
            router.post(`/admin/canvas/oauth/revoke/${integration.id}`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Authorization revoked successfully');
                },
                onError: () => {
                    toast.error('Failed to revoke authorization');
                },
            });
        },
    });
};

const getSyncStatusBadge = (status: string) => {
    const variants: Record<string, any> = {
        idle: { variant: 'secondary', icon: Clock, text: 'Idle' },
        syncing: { variant: 'default', icon: RefreshCw, text: 'Syncing' },
        completed: { variant: 'success', icon: CheckCircle2, text: 'Completed' },
        failed: { variant: 'destructive', icon: XCircle, text: 'Failed' },
    };
    return variants[status] || variants.idle;
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <Head title="Canvas Integrations" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Canvas LMS Integrations</h1>
                <p class="text-muted-foreground mt-2">
                    Connect and sync courses from Canvas LMS
                </p>
            </div>

            <Dialog v-model:open="showCreateDialog">
                <DialogTrigger as-child>
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Add Integration
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-[525px]">
                    <DialogHeader>
                        <DialogTitle>Add Canvas Integration</DialogTitle>
                        <DialogDescription>
                            Configure Canvas LMS integration for your campus. Get your credentials from Canvas Developer Keys.
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="createIntegration" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="canvas_url">Canvas URL</Label>
                            <Input
                                id="canvas_url"
                                v-model="form.canvas_url"
                                placeholder="https://canvas.instructure.com"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="client_id">Client ID</Label>
                            <Input
                                id="client_id"
                                v-model="form.client_id"
                                placeholder="Enter Canvas Client ID"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="client_secret">Client Secret</Label>
                            <Input
                                id="client_secret"
                                v-model="form.client_secret"
                                type="password"
                                placeholder="Enter Canvas Client Secret"
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="showCreateDialog = false">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="isSubmitting">
                                {{ isSubmitting ? 'Creating...' : 'Create Integration' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Integrations List -->
        <div v-if="integrations.length === 0" class="text-center py-12">
            <AlertCircle class="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 class="mt-4 text-lg font-semibold">No integrations yet</h3>
            <p class="text-muted-foreground mt-2">Get started by adding your first Canvas integration.</p>
            <Button class="mt-4" @click="showCreateDialog = true">
                <Plus class="mr-2 h-4 w-4" />
                Add Integration
            </Button>
        </div>

        <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="integration in integrations" :key="integration.id">
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div class="space-y-1 flex-1">
                            <CardTitle class="flex items-center gap-2">
                                Canvas LMS Integration
                                <Badge v-if="integration.is_active" variant="success">Active</Badge>
                                <Badge v-else variant="secondary">Inactive</Badge>
                            </CardTitle>
                            <CardDescription>
                                <a :href="integration.canvas_url" target="_blank" class="flex items-center gap-1 hover:underline">
                                    {{ integration.canvas_url }}
                                    <ExternalLink class="h-3 w-3" />
                                </a>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Authorization Status -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Authorization</span>
                        <Badge v-if="integration.has_token && !isTokenExpired(integration)" variant="success">
                            <CheckCircle2 class="mr-1 h-3 w-3" />
                            Authorized
                        </Badge>
                        <Badge v-else-if="integration.has_token && isTokenExpired(integration) && canAutoRefresh(integration)" variant="default">
                            <RefreshCw class="mr-1 h-3 w-3" />
                            Auto-Refresh
                        </Badge>
                        <Badge v-else-if="integration.has_token && isTokenExpired(integration)" variant="warning">
                            <AlertCircle class="mr-1 h-3 w-3" />
                            Token Expired
                        </Badge>
                        <Badge v-else variant="secondary">
                            <XCircle class="mr-1 h-3 w-3" />
                            Not Authorized
                        </Badge>
                    </div>
                    
                    <!-- Token Info -->
                    <div v-if="integration.has_token && isTokenExpired(integration) && canAutoRefresh(integration)" class="text-xs text-muted-foreground bg-muted/50 p-2 rounded">
                        Access token will be automatically refreshed when needed.
                    </div>
                    <div v-else-if="integration.has_token && isTokenExpired(integration) && !canAutoRefresh(integration)" class="text-xs text-warning bg-warning/10 p-2 rounded">
                        Access token has expired and refresh token is not available. Please re-authorize.
                    </div>

                    <!-- Sync Status -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Sync Status</span>
                        <Badge :variant="getSyncStatusBadge(integration.sync_status).variant">
                            <component :is="getSyncStatusBadge(integration.sync_status).icon" class="mr-1 h-3 w-3" />
                            {{ getSyncStatusBadge(integration.sync_status).text }}
                        </Badge>
                    </div>

                    <!-- Last Sync -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Last Sync</span>
                        <span class="text-sm">{{ formatDate(integration.last_sync_at) }}</span>
                    </div>

                    <!-- Sync Error -->
                    <div v-if="integration.sync_error" class="text-xs text-destructive bg-destructive/10 p-2 rounded">
                        {{ integration.sync_error }}
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-2 pt-2">
                        <Button
                            v-if="needsManualAuth(integration)"
                            @click="authorizeIntegration(integration)"
                            size="sm"
                            class="w-full"
                            variant="default"
                        >
                            <ExternalLink class="mr-2 h-4 w-4" />
                            {{ integration.has_token ? 'Re-authorize' : 'Authorize' }}
                        </Button>
                        
                        <template v-else>
                            <!-- Retry button when sync failed -->
                            <Button
                                v-if="integration.sync_status === 'failed' || integration.sync_error"
                                @click="syncCourses(integration)"
                                size="sm"
                                class="w-full"
                                variant="default"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" />
                                Retry Sync
                            </Button>
                            
                            <!-- Normal sync button -->
                            <Button
                                v-else
                                @click="syncCourses(integration)"
                                size="sm"
                                class="w-full"
                                :disabled="integration.sync_status === 'syncing'"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': integration.sync_status === 'syncing' }" />
                                {{ integration.sync_status === 'syncing' ? 'Syncing...' : 'Sync Courses' }}
                            </Button>
                            
                            <div class="flex gap-2">
                                <Button
                                    @click="authorizeIntegration(integration)"
                                    size="sm"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    <ExternalLink class="mr-2 h-4 w-4" />
                                    Reconnect
                                </Button>
                                
                                <Button
                                    @click="revokeAuthorization(integration)"
                                    size="sm"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    Revoke
                                </Button>
                            </div>
                        </template>

                        <div class="flex gap-2">
                            <Button
                                @click="toggleActive(integration)"
                                size="sm"
                                variant="outline"
                                class="flex-1"
                            >
                                <Power class="mr-2 h-4 w-4" />
                                {{ integration.is_active ? 'Deactivate' : 'Activate' }}
                            </Button>
                            
                            <Button
                                @click="deleteIntegration(integration)"
                                size="sm"
                                variant="destructive"
                                class="flex-1"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Help Section -->
        <Card>
            <CardHeader>
                <CardTitle>Getting Started</CardTitle>
                <CardDescription>Follow these steps to set up Canvas integration</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-xs font-bold">
                            1
                        </div>
                        <div>
                            <p class="font-medium">Create Developer Key in Canvas</p>
                            <p class="text-sm text-muted-foreground">
                                Go to Canvas Admin → Developer Keys → + API Key and configure your application
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-xs font-bold">
                            2
                        </div>
                        <div>
                            <p class="font-medium">Add Integration</p>
                            <p class="text-sm text-muted-foreground">
                                Click "Add Integration" above and enter your Canvas URL, Client ID, and Client Secret
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-xs font-bold">
                            3
                        </div>
                        <div>
                            <p class="font-medium">Authorize & Sync</p>
                            <p class="text-sm text-muted-foreground">
                                Click "Authorize" to connect to Canvas, then "Sync Courses" to import your courses
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
