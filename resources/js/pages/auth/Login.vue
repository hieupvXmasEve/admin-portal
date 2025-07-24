<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { AUTH_ROUTE_NAMES } from '@/constants/auth-routes';
import { onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const handleGoogleSignIn = () => {
    // Google OAuth implementation would go here
    console.log('Initiating Google sign-in for Swinburne Vietnam...');
    window.location.href = route(AUTH_ROUTE_NAMES.GOOGLE_LOGIN);
};
const errorMessage = ref('');
// http://127.0.0.1:8000/login?email=hieupv2412%40gmail.com&error=Email%20is%20not%20registered
// get error from url
onMounted(() => {
    const error = new URLSearchParams(window.location.search).get('error');
    const email = new URLSearchParams(window.location.search).get('email');
    if (error) {
        errorMessage.value = email + ' ' + error;
    }
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-50 via-gray-50 to-red-50 p-4">
        <div class="w-full max-w-lg">
            <!-- University Header -->
            <div class="mb-8 text-center">
                <div class="mx-auto mb-6 flex h-32 w-32 items-center justify-center rounded-2xl border-4 border-white shadow-2xl">
                    <img src="/logo-login.png" alt="Swinburne Vietnam University Logo" class="size-full text-white" />
                </div>
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900">Swinburne Vietnam</h1>
                    <p class="text-lg font-medium text-red-700">University Portal</p>
                    <p class="text-sm text-gray-600">Administrator & Staff Access System</p>
                </div>
            </div>

            <!-- Main Login Card -->
            <Card class="overflow-hidden border-0 bg-white/90 shadow-2xl backdrop-blur-md">
                <div class="h-2 bg-gradient-to-r from-red-600 via-red-700 to-black"></div>

                <CardHeader class="pt-8 pb-6 text-center">
                    <CardTitle class="mb-2 text-2xl font-bold text-gray-900">Welcome Back</CardTitle>
                    <CardDescription class="text-base text-gray-600">
                        Access your administrative dashboard with your institutional account
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-8 px-8 pb-8">
                    <!-- Google Sign-in Button -->
                    <div class="space-y-4">
                        <div v-if="errorMessage" class="text-red-500">{{ errorMessage }}</div>
                        <Button
                            @click="handleGoogleSignIn"
                            class="group h-14 w-full border-2 border-gray-200 bg-white text-base font-semibold text-gray-800 shadow-lg transition-all duration-300 hover:border-red-300 hover:bg-gray-50 hover:shadow-xl"
                            variant="outline"
                        >
                            <div class="flex items-center justify-center space-x-4">
                                <svg class="h-6 w-6 transition-transform group-hover:scale-110" viewBox="0 0 24 24">
                                    <path
                                        fill="#4285F4"
                                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                    />
                                    <path
                                        fill="#34A853"
                                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                    />
                                    <path
                                        fill="#FBBC05"
                                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                    />
                                    <path
                                        fill="#EA4335"
                                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                    />
                                </svg>
                                <span>Sign in with Google</span>
                            </div>
                        </Button>

                        <p class="text-center text-xs text-gray-500">Use your @swinburne.edu.vn or authorized institutional account</p>
                    </div>

                    <Separator class="my-8" />

                    <!-- Support Section -->
                    <div class="space-y-3 border-t border-gray-100 pt-4 text-center">
                        <p class="text-sm text-gray-600">Need assistance with your account?</p>
                        <div class="flex justify-center space-x-6 text-sm">
                            <button class="font-medium text-red-600 underline underline-offset-2 transition-colors hover:text-red-700">
                                IT Support
                            </button>
                            <button class="font-medium text-red-600 underline underline-offset-2 transition-colors hover:text-red-700">
                                Help Center
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Footer -->
            <div class="mt-8 space-y-4 text-center">
                <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                    <span>🇻🇳</span>
                    <span>Vietnam</span>
                </div>
                <p class="text-sm text-gray-500">© 2024 Swinburne Vietnam University. All rights reserved.</p>
                <div class="flex justify-center space-x-6 text-xs text-gray-400">
                    <button class="transition-colors hover:text-red-600">Privacy Policy</button>
                    <button class="transition-colors hover:text-red-600">Terms of Service</button>
                    <button class="transition-colors hover:text-red-600">Accessibility</button>
                </div>
            </div>
        </div>
    </div>
</template>
