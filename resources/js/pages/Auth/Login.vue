<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useSystemConfig } from '@/composables/useSystemConfig';
import { AUTH_ROUTE_NAMES } from '@/constants/auth-routes';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, ImageOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    error?: string;
    email?: string;
}

const props = withDefaults(defineProps<Props>(), {
    error: '',
    email: '',
});

// System configuration
const { systemConfig } = useSystemConfig();
const monogram = computed(() => systemConfig.value.app_name.trim().slice(0, 1).toUpperCase());

const isLoading = ref(false);

const errorMessage = computed(() => {
    if (props.error && props.email) {
        return `${props.email}: ${props.error}`;
    }
    return props.error || '';
});

const handleGoogleSignIn = async () => {
    try {
        isLoading.value = true;
        window.location.href = route(AUTH_ROUTE_NAMES.GOOGLE_LOGIN);
    } catch (error) {
        console.error('Google sign-in error:', error);
    } finally {
        isLoading.value = false;
    }
};
</script>
<template>
    <Head title="Login" />
    <div class="from-background via-muted/20 to-muted/40 flex min-h-screen items-center justify-center bg-gradient-to-br p-4">
        <div class="w-full max-w-lg">
            <!-- University Header -->
            <header class="mb-8 text-center">
                <div class="border-background mx-auto mb-6 flex size-44 items-center justify-center rounded-2xl border-4 shadow-2xl backdrop-blur-sm">
                    <img v-if="systemConfig.logo_full_url" :src="systemConfig.logo_full_url" :alt="`${systemConfig.app_name} logo`" class="size-full object-contain" />
                    <div v-else class="text-muted-foreground flex flex-col items-center gap-2 text-sm" role="status">
                        <ImageOff class="size-6" />
                        <span class="font-semibold">{{ monogram }}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <h1 class="text-foreground text-3xl font-bold tracking-tight">{{ systemConfig.app_name }}</h1>
                    <p class="text-muted-foreground text-sm">Administrator & Staff Access System</p>
                </div>
            </header>

            <!-- Main Login Card -->
            <Card class="bg-card/95 overflow-hidden border-0 shadow-2xl backdrop-blur-md">
                <div class="from-primary via-primary/80 to-primary/60 h-2 bg-gradient-to-r"></div>

                <CardHeader class="pt-8 pb-6 text-center">
                    <CardTitle class="text-card-foreground mb-2 text-2xl font-bold"> Welcome Back </CardTitle>
                    <CardDescription class="text-muted-foreground text-base"> Access your administrative dashboard with your institutional account </CardDescription>
                </CardHeader>

                <CardContent class="space-y-8 px-8 pb-8">
                    <!-- Error Alert -->
                    <Alert v-if="errorMessage" variant="destructive" class="mb-6">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertDescription>
                            {{ errorMessage }}
                        </AlertDescription>
                    </Alert>

                    <!-- Google Sign-in Form -->
                    <form @submit.prevent="handleGoogleSignIn" class="space-y-6">
                        <div class="space-y-4">
                            <Button
                                type="submit"
                                :disabled="isLoading"
                                class="group border-border bg-background text-foreground hover:border-primary hover:bg-accent relative h-14 w-full overflow-hidden border-2 text-base font-semibold shadow-lg transition-all duration-300 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50"
                                variant="outline"
                            >
                                <div class="flex items-center justify-center space-x-4">
                                    <svg class="h-6 w-6 transition-transform group-hover:scale-110" viewBox="0 0 24 24" :class="{ 'animate-spin': isLoading }">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                                    </svg>
                                    <span>{{ isLoading ? 'Signing in...' : 'Sign in with Google' }}</span>
                                </div>
                            </Button>
                            <!--                            <p class="text-muted-foreground text-center text-xs">Use your @asia.edu.vn or authorized institutional account</p>-->
                        </div>
                    </form>

                    <Separator class="my-6" />

                    <!-- Support Section -->
                    <div class="space-y-3 text-center">
                        <p class="text-muted-foreground text-sm">Need assistance with your account?</p>
                        <nav class="flex justify-center space-x-6 text-sm" aria-label="Support links">
                            <button type="button" class="text-primary hover:text-primary/80 focus:ring-ring font-medium underline underline-offset-2 transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-none">IT Support</button>
                            <button type="button" class="text-primary hover:text-primary/80 focus:ring-ring font-medium underline underline-offset-2 transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-none">Help Center</button>
                        </nav>
                    </div>
                </CardContent>
            </Card>

            <!-- Footer -->
            <footer class="mt-8 space-y-4 text-center">
                <div class="text-muted-foreground flex items-center justify-center space-x-2 text-sm">
                    <span>{{ systemConfig.country }}</span>
                </div>
                <p class="text-muted-foreground text-sm">{{ systemConfig.copyright_text }}</p>
                <nav class="text-muted-foreground flex justify-center space-x-6 text-xs" aria-label="Legal links">
                    <button type="button" class="hover:text-primary focus:ring-ring transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-none">Privacy Policy</button>
                    <button type="button" class="hover:text-primary focus:ring-ring transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-none">Terms of Service</button>
                    <button type="button" class="hover:text-primary focus:ring-ring transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-none">Accessibility</button>
                </nav>
            </footer>
        </div>
    </div>
</template>
