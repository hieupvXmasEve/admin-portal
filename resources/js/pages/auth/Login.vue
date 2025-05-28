<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    error?: string;
    email?: string;
}>();

// const form = useForm({
//     email: '',
//     password: '',
//     remember: false,
// });

// const submit = () => {
//     form.post(route('login'), {
//         onFinish: () => form.reset('password'),
//     });
// };
const loginWithGoogle = () => {
    window.location.href = route('google.login');
};
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <Button
            type="button"
            class="flex w-full cursor-pointer items-center justify-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-100"
            @click="loginWithGoogle"
        >
            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google" class="h-5 w-5" />
            Đăng nhập với Google
        </Button>
        <!-- show error -->
        <div v-if="error" class="mb-2 text-red-500">
            <span class="font-bold">{{ email }}</span> - {{ error }}
        </div>
    </AuthBase>
</template>
