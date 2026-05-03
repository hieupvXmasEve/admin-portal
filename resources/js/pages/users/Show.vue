<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { route } from 'ziggy-js';
import { 
    ArrowLeft, 
    User as UserIcon, 
    Mail, 
    Phone, 
    MapPin, 
    Calendar,
    Shield,
    Building,
    Users,
    Loader2
} from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    phone?: string;
    address?: string;
    status: string;
    type: string;
    department_id?: number;
    department?: {
        id: number;
        name: string;
    };
    last_login_at?: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    user: User;
}

const props = defineProps<Props>();

// Loading state
const isLoading = ref(false);

// User type filter
const selectedUserType = ref<string>('all');

// Available user types
const userTypes = [
    { value: 'all', label: 'All Users' },
    { value: 'staff', label: 'Staff' },
    { value: 'student', label: 'Student' },
    { value: 'lecturer', label: 'Lecturer' },
    { value: 'parent', label: 'Parent' },
];

// Computed properties
const userTypeLabel = computed(() => {
    const type = userTypes.find(t => t.value === props.user.type);
    return type ? type.label : props.user.type;
});

const statusLabel = computed(() => {
    return props.user.status.charAt(0).toUpperCase() + props.user.status.slice(1);
});

const statusColor = computed(() => {
    const colors: Record<string, string> = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        pending: 'bg-yellow-100 text-yellow-800',
        suspended: 'bg-red-100 text-red-800',
        banned: 'bg-red-100 text-red-800',
        locked: 'bg-orange-100 text-orange-800',
        verified: 'bg-blue-100 text-blue-800',
        unverified: 'bg-gray-100 text-gray-800',
    };
    return colors[props.user.status] || 'bg-gray-100 text-gray-800';
});

// Methods
const formatDateTime = (dateString?: string) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleString();
};

const goBack = () => {
    window.history.back();
};

// Filter users by type (this would typically make an API call)
const filterByUserType = async (type: string) => {
    selectedUserType.value = type;
    isLoading.value = true;
    
    // Simulate API call - in real implementation, this would call the backend
    setTimeout(() => {
        isLoading.value = false;
        // In a real implementation, you would update the users list here
        // or redirect to the index page with the filter applied
        if (type !== 'all') {
            window.location.href = route('identity.users.index', { type });
        }
    }, 500);
};
</script>

<template>
    <Head :title="`${user.name} - User Details`" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center space-x-4">
                        <button
                            @click="goBack"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Back
                        </button>
                        <h1 class="text-xl font-semibold text-gray-900">User Details</h1>
                    </div>
                    
                    <!-- User Type Filter -->
                    <div class="flex items-center space-x-4">
                        <label class="text-sm font-medium text-gray-700">Filter by Type:</label>
                        <select
                            v-model="selectedUserType"
                            @change="filterByUserType(selectedUserType)"
                            class="block w-40 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                        >
                            <option
                                v-for="type in userTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        
                        <!-- Loading indicator -->
                        <div v-if="isLoading" class="flex items-center">
                            <Loader2 class="h-4 w-4 animate-spin text-blue-600" />
                            <span class="ml-2 text-sm text-gray-600">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- User Profile Card -->
                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-20 w-20 rounded-full bg-blue-100 flex items-center justify-center">
                                        <UserIcon class="h-10 w-10 text-blue-600" />
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">{{ user.name }}</h3>
                                    <p class="text-sm text-gray-500">{{ userTypeLabel }}</p>
                                    <span :class="`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}`">
                                        {{ statusLabel }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div class="flex items-center">
                                    <Mail class="h-4 w-4 text-gray-400 mr-3" />
                                    <span class="text-sm text-gray-900">{{ user.email }}</span>
                                </div>
                                
                                <div v-if="user.phone" class="flex items-center">
                                    <Phone class="h-4 w-4 text-gray-400 mr-3" />
                                    <span class="text-sm text-gray-900">{{ user.phone }}</span>
                                </div>
                                
                                <div v-if="user.address" class="flex items-center">
                                    <MapPin class="h-4 w-4 text-gray-400 mr-3" />
                                    <span class="text-sm text-gray-900">{{ user.address }}</span>
                                </div>
                                
                                <div v-if="user.department" class="flex items-center">
                                    <Building class="h-4 w-4 text-gray-400 mr-3" />
                                    <span class="text-sm text-gray-900">{{ user.department.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details and Actions -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Account Information -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Account Information
                            </h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">User ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">#{{ user.id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ userTypeLabel }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span :class="`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}`">
                                            {{ statusLabel }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(user.last_login_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(user.created_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Updated At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(user.updated_at) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Actions
                            </h3>
                            <div class="flex flex-wrap gap-3">
                                <Link
                                    :href="route('identity.users.edit', user.id)"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <Shield class="h-4 w-4 mr-2" />
                                    Edit User
                                </Link>
                                
                                <button
                                    @click="filterByUserType(user.type)"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <Users class="h-4 w-4 mr-2" />
                                    View Similar Users
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>