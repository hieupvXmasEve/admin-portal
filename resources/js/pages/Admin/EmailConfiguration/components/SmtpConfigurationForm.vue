<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
    EyeIcon,
    EyeOffIcon,
    TestTubeIcon,
    CheckCircleIcon,
    XCircleIcon,
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { 
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert'
import { toTypedSchema } from '@vee-validate/zod'
import * as z from 'zod'
import { useForm } from 'vee-validate'
import { 
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage
} from '@/components/ui/form'
import { useSmtpConfiguration } from '@/composables/useSmtpConfiguration'

interface Props {
    configuration?: any
    isOpen: boolean
}

interface Emits {
    (e: 'submit', values: any): void
    (e: 'cancel'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { testConnectionWithData } = useSmtpConfiguration()

const isEditing = computed(() => !!props.configuration)

const formSchema = toTypedSchema(z.object({
    name: z.string().min(1, 'Configuration name is required'),
    host: z.string().min(1, 'SMTP host is required'),
    port: z.number().min(1).max(65535),
    username: z.string().optional(),
    password: z.string().optional(),
    encryption: z.enum(['tls', 'ssl', 'none']),
    from_address: z.string().email('Invalid email address'),
    from_name: z.string().min(1, 'From name is required'),
    daily_limit: z.number().min(1),
    rate_limit: z.number().min(1),
    is_active: z.boolean(),
}))

const { handleSubmit, setValues, values, resetForm } = useForm({
    validationSchema: formSchema,
})

const isTesting = ref(false)
const testResult = ref<any>(null)
const showPassword = ref(false)

const canTest = computed(() => {
    return values.host && values.port && values.from_address
})

// Watch for modal open state and configuration changes
watch(() => [props.isOpen, props.configuration], ([isOpen, config]) => {
    if (isOpen) {
        // Reset test result when modal opens
        testResult.value = null
        
        if (config) {
            // Editing existing configuration
            setValues({
                name: config.name || '',
                host: config.host || '',
                port: config.port || 587,
                username: config.username || '',
                password: '', // Don't populate password for security
                encryption: config.encryption || 'tls',
                from_address: config.from_address || '',
                from_name: config.from_name || '',
                daily_limit: config.daily_limit || 1000,
                rate_limit: config.rate_limit || 50,
                is_active: config.is_active || false,
            });
        } else {
            // Creating new configuration
            setValues({
                name: '',
                host: '',
                port: 587,
                username: '',
                password: '',
                encryption: 'tls',
                from_address: '',
                from_name: '',
                daily_limit: 1000,
                rate_limit: 50,
                is_active: false,
            });
        }
    }
}, { immediate: true });

const testConnection = async () => {
    if (!canTest.value) return

    isTesting.value = true
    testResult.value = null

    try {
        const result = await testConnectionWithData(values)
        testResult.value = result
    } catch (error) {
        testResult.value = {
            success: false,
            message: 'Failed to test connection',
            technical_details: error instanceof Error ? error.message : 'Unknown error'
        }
    } finally {
        isTesting.value = false
    }
}

const onSubmit = handleSubmit((formValues) => {
    emit('submit', formValues)
})

const onCancel = () => {
    emit('cancel')
}
</script>

<template>
    <form @submit.prevent="onSubmit" class="space-y-6">
        <FormField name="name" v-slot="{ componentField }">
            <FormItem class="col-span-2">
                <FormLabel>Configuration Name *</FormLabel>
                <FormControl>
                    <Input v-bind="componentField" placeholder="e.g., Production SMTP" />
                </FormControl>
                <FormMessage />
            </FormItem>
        </FormField>
        
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <FormField name="host" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>SMTP Host *</FormLabel>
                    <FormControl>
                        <Input v-bind="componentField" placeholder="smtp.example.com" />
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>
            <FormField name="port" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Port *</FormLabel>
                    <FormControl>
                        <Input type="number" v-bind="componentField" placeholder="587" />
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>
        </div>
        
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <FormField name="encryption" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Encryption *</FormLabel>
                    <Select v-bind="componentField">
                        <FormControl>
                            <SelectTrigger>
                                <SelectValue placeholder="Select encryption" />
                            </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                            <SelectItem value="tls">TLS (recommended)</SelectItem>
                            <SelectItem value="ssl">SSL</SelectItem>
                            <SelectItem value="none">None</SelectItem>
                        </SelectContent>
                    </Select>
                    <FormMessage />
                </FormItem>
            </FormField>
            <FormField name="username" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Username</FormLabel>
                    <FormControl>
                        <Input v-bind="componentField" placeholder="your-email@domain.com" />
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>
        </div>
        
        <div>
            <FormField name="password" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Password {{ isEditing ? '(leave blank to keep current)' : '*' }}</FormLabel>
                    <FormControl>
                        <div class="relative">
                            <Input :type="showPassword ? 'text' : 'password'" v-bind="componentField" placeholder="Enter SMTP password" />
                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <EyeIcon v-if="!showPassword" class="h-4 w-4 text-muted-foreground" />
                                <EyeOffIcon v-else class="h-4 w-4 text-muted-foreground" />
                            </button>
                        </div>
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>
        </div>
        
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <FormField name="from_address" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>From Email Address *</FormLabel>
                    <FormControl>
                        <Input type="email" v-bind="componentField" placeholder="noreply@yourdomain.com" />
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>
            <FormField name="from_name" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>From Name *</FormLabel>
                    <FormControl>
                        <Input v-bind="componentField" placeholder="Your Organization" />
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>
        </div>

        <!-- Limits -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <FormField name="daily_limit" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Daily Limit *</FormLabel>
                    <FormControl>
                        <Input type="number" v-bind="componentField" placeholder="1000" />
                    </FormControl>
                    <FormDescription>Maximum emails per day</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>
            <FormField name="rate_limit" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Rate Limit *</FormLabel>
                    <FormControl>
                        <Input type="number" v-bind="componentField" placeholder="50" />
                    </FormControl>
                    <FormDescription>Emails per hour</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>
        </div>

        <!-- Active Status -->
        <FormField name="is_active" v-slot="{ field }">
            <FormItem class="flex flex-row items-center space-x-3 space-y-0">
                <FormControl>
                    <input type="checkbox" v-bind="field" />
                </FormControl>
                <FormLabel class="font-normal">
                    Set as active configuration
                </FormLabel>
            </FormItem>
        </FormField>

        <!-- Test Connection -->
        <div v-if="!isEditing || values.password" class="bg-muted rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-medium">Test Connection</h4>
                    <p class="text-sm text-muted-foreground">
                        Test the SMTP connection before saving
                    </p>
                </div>
                <Button
                    type="button"
                    @click="testConnection"
                    :disabled="isTesting || !canTest"
                    variant="outline"
                >
                    <TestTubeIcon :class="['h-4 w-4 mr-2', { 'animate-spin': isTesting }]" />
                    {{ isTesting ? 'Testing...' : 'Test Connection' }}
                </Button>
            </div>

            <div v-if="testResult" class="mt-3">
                <Alert :variant="testResult.success ? 'default' : 'destructive'">
                    <CheckCircleIcon v-if="testResult.success" class="h-4 w-4" />
                    <XCircleIcon v-else class="h-4 w-4" />
                    <AlertTitle>{{ testResult.success ? 'Success' : 'Error' }}</AlertTitle>
                    <AlertDescription>
                        {{ testResult.message }}
                        <template v-if="testResult.technical_details && !testResult.success">
                            <br> Technical details: {{ testResult.technical_details }}
                        </template>
                    </AlertDescription>
                </Alert>
            </div>
        </div>

        <!-- Form Actions Slot -->
        <slot name="actions" :submit="onSubmit" :cancel="onCancel" :is-editing="isEditing">
            <div class="flex justify-end space-x-3 pt-6 border-t">
                <Button type="button" variant="outline" @click="onCancel">Cancel</Button>
                <Button type="submit">
                    {{ isEditing ? 'Update Configuration' : 'Create Configuration' }}
                </Button>
            </div>
        </slot>
    </form>
</template>
