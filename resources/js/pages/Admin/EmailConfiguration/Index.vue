<script setup lang="ts">
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useSmtpConfiguration } from '@/composables/useSmtpConfiguration';
import { CheckCircleIcon, ClockIcon, PencilIcon, PlusIcon, RefreshCwIcon, ServerIcon, TestTubeIcon, TrashIcon, TrendingUpIcon } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import AddSmtpConfigurationModal from './components/AddSmtpConfigurationModal.vue';
import EditSmtpConfigurationModal from './components/EditSmtpConfigurationModal.vue';
interface EmailConfiguration {
    id: number;
    name: string;
    host: string;
    port: number;
    username?: string;
    encryption: string;
    from_address: string;
    from_name: string;
    is_active: boolean;
    daily_limit: number;
    rate_limit: number;
    last_tested_at?: string;
    test_result?: string;
}

interface Statistics {
    total_configurations: number;
    active_configurations: number;
    tested_configurations: number;
    successful_tests: number;
    test_success_rate: number;
}

const { configurations, statistics, isLoading, isRefreshing, testingConfigs, activatingConfigs, loadConfigurations, testConnection, setActiveConfiguration, deleteConfiguration: deleteConfig } = useSmtpConfiguration();

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const editingConfiguration = ref<EmailConfiguration | null>(null);
const deletingConfiguration = ref<EmailConfiguration | null>(null);

onMounted(() => {
    loadConfigurations();
});

const refreshConfigurations = () => {
    loadConfigurations();
};

const editConfiguration = (config: EmailConfiguration) => {
    editingConfiguration.value = config;
    showEditModal.value = true;
};

const deleteConfiguration = (config: EmailConfiguration) => {
    deletingConfiguration.value = config;
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (deletingConfiguration.value) {
        await deleteConfig(deletingConfiguration.value.id);
        deletingConfiguration.value = null;
        showDeleteModal.value = false;
    }
};

const setActive = (config: EmailConfiguration) => {
    setActiveConfiguration(config.id);
};

const onAddConfigurationSaved = () => {
    showAddModal.value = false;
    loadConfigurations();
};

const onEditConfigurationSaved = () => {
    showEditModal.value = false;
    editingConfiguration.value = null;
    loadConfigurations();
};

const onEditModalClose = () => {
    // Don't reset editingConfiguration immediately to avoid form data loss
    // Reset it after a short delay to ensure the modal has fully closed
    setTimeout(() => {
        editingConfiguration.value = null;
    }, 100);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">SMTP Configuration</h1>
                <p class="mt-1 text-sm text-gray-500">Manage email server settings and test connections</p>
            </div>
            <div class="flex items-center space-x-3">
                <Button @click="refreshConfigurations" :disabled="isRefreshing" variant="outline">
                    <RefreshCwIcon :class="['mr-2 h-4 w-4', { 'animate-spin': isRefreshing }]" />
                    Refresh
                </Button>
                <Button @click="showAddModal = true">
                    <PlusIcon class="mr-2 h-4 w-4" />
                    Add Configuration
                </Button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium"> Total Configurations </CardTitle>
                    <ServerIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ statistics.total_configurations || 0 }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium"> Active Configurations </CardTitle>
                    <CheckCircleIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ statistics.active_configurations || 0 }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Tested Configurations</CardTitle>
                    <TestTubeIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ statistics.tested_configurations || 0 }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Success Rate</CardTitle>
                    <TrendingUpIcon class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.test_success_rate || 0 }}%</div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Email Configurations</CardTitle>
                <CardDescription> Manage your SMTP server configurations and test connections. </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="isLoading" class="p-6">
                    <div class="animate-pulse space-y-4">
                        <div v-for="i in 3" :key="i" class="bg-muted h-16 rounded"></div>
                    </div>
                </div>
                <ul v-else-if="configurations.length > 0" class="divide-y">
                    <li v-for="config in configurations" :key="config.id" class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div :class="['h-3 w-3 rounded-full', config.is_active ? 'bg-green-500' : 'bg-gray-400']"></div>
                                </div>
                                <div class="ml-4">
                                    <div class="flex items-center">
                                        <p class="text-sm font-medium">
                                            {{ config.name }}
                                        </p>
                                        <span v-if="config.is_active" class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"> Active </span>
                                    </div>
                                    <div class="text-muted-foreground mt-1 flex items-center text-sm">
                                        <span>{{ config.host }}:{{ config.port }}</span>
                                        <span class="mx-2">•</span>
                                        <span>{{ config.from_address }}</span>
                                        <span class="mx-2">•</span>
                                        <span class="capitalize">{{ config.encryption }}</span>
                                    </div>
                                    <div v-if="config.last_tested_at" class="text-muted-foreground mt-1 flex items-center text-xs">
                                        <ClockIcon class="mr-1 h-3 w-3" />
                                        Last tested: {{ formatDate(config.last_tested_at) }}
                                        <span v-if="config.test_result" class="ml-2">
                                            <span :class="[config.test_result === 'success' ? 'text-green-600' : 'text-red-600']">
                                                {{ config.test_result === 'success' ? '✓ Success' : '✗ Failed' }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Button @click="testConnection(config)" :disabled="testingConfigs.has(config.id)" size="sm" variant="outline">
                                    <TestTubeIcon :class="['mr-1 h-3 w-3', { 'animate-spin': testingConfigs.has(config.id) }]" />
                                    Test
                                </Button>
                                <Button v-if="!config.is_active" @click="setActive(config)" :disabled="activatingConfigs.has(config.id)" size="sm">
                                    <CheckCircleIcon :class="['mr-1 h-3 w-3', { 'animate-spin': activatingConfigs.has(config.id) }]" />
                                    Activate
                                </Button>
                                <Button @click="editConfiguration(config)" size="sm" variant="outline">
                                    <PencilIcon class="mr-1 h-3 w-3" />
                                    Edit
                                </Button>
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button :disabled="config.is_active" size="sm" variant="destructive">
                                            <TrashIcon class="mr-1 h-3 w-3" />
                                            Delete
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Delete Configuration</AlertDialogTitle>
                                            <AlertDialogDescription> Are you sure you want to delete the configuration '{{ config.name }}'? This action cannot be undone. </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel @click="deletingConfiguration = null">Cancel</AlertDialogCancel>
                                            <AlertDialogAction @click="deleteConfiguration(config)">Delete</AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </div>
                    </li>
                </ul>
                <div v-else class="p-6 text-center">
                    <ServerIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium">No configurations</h3>
                    <p class="text-muted-foreground mt-1 text-sm">Get started by creating your first SMTP configuration.</p>
                    <div class="mt-6">
                        <Button @click="showAddModal = true">
                            <PlusIcon class="mr-2 h-4 w-4" />
                            Add Configuration
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Add Modal -->
        <AddSmtpConfigurationModal v-model:open="showAddModal" @saved="onAddConfigurationSaved" />

        <!-- Edit Modal -->
        <EditSmtpConfigurationModal
            v-model:open="showEditModal"
            :configuration="editingConfiguration"
            @saved="onEditConfigurationSaved"
            @update:open="
                (open) => {
                    showEditModal = open;
                    if (!open) onEditModalClose();
                }
            "
        />

        <!-- Delete Confirmation Modal -->
        <AlertDialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Configuration</AlertDialogTitle>
                    <AlertDialogDescription> Are you sure you want to delete the configuration '{{ deletingConfiguration?.name }}'? This action cannot be undone. </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="showDeleteModal = false">Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete">Delete</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
