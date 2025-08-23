<script setup lang="ts">
import { ref } from 'vue';
import ImageUpload from '@/components/ImageUpload.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { UploadRecord } from '@/types/imageUpload';

// State
const uploadResults = ref<Record<string, UploadRecord[]>>({});
const uploadErrors = ref<Record<string, string>>({});

// Event handlers
const handleUploadSuccess = (context: string, files: UploadRecord[]) => {
    uploadResults.value[context] = files;
    console.log(`Upload success for ${context}:`, files);
};

const handleUploadError = (context: string, error: string) => {
    uploadErrors.value[context] = error;
    console.error(`Upload error for ${context}:`, error);
};

const handleConfigError = (context: string, error: string) => {
    console.warn(`Config error for ${context}:`, error);
};

const clearResults = (context: string) => {
    delete uploadResults.value[context];
    delete uploadErrors.value[context];
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Image Upload Examples</h2>
            <p class="text-muted-foreground">
                Examples of the ImageUpload component with different configurations and contexts.
            </p>
        </div>

        <Tabs default-value="avatar" class="w-full">
            <TabsList class="grid w-full grid-cols-3">
                <TabsTrigger value="avatar">Avatar Upload</TabsTrigger>
                <TabsTrigger value="assignment">Assignment Images</TabsTrigger>
                <TabsTrigger value="general">General Images</TabsTrigger>
            </TabsList>

            <!-- Avatar Upload -->
            <TabsContent value="avatar" class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            Avatar Upload
                            <Badge variant="secondary">Single File</Badge>
                        </CardTitle>
                        <CardDescription>
                            Upload profile pictures with automatic configuration from the avatar context.
                            Supports JPEG, PNG, and WebP formats up to 2MB.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImageUpload
                            context="avatar"
                            :immediate="true"
                            class="mb-4"
                            @upload-success="(files) => handleUploadSuccess('avatar', files)"
                            @upload-error="(error) => handleUploadError('avatar', error)"
                            @config-error="(error) => handleConfigError('avatar', error)"
                        />

                        <!-- Results -->
                        <div v-if="uploadResults.avatar" class="mt-4 p-4 bg-green-50 rounded-lg">
                            <h4 class="font-medium text-green-800 mb-2">Upload Successful!</h4>
                            <div v-for="file in uploadResults.avatar" :key="file.id" class="text-sm text-green-700">
                                <p><strong>File:</strong> {{ file.original_name }}</p>
                                <p><strong>URL:</strong> <a :href="file.url" target="_blank" class="underline">{{ file.url }}</a></p>
                            </div>
                            <Button variant="outline" size="sm" class="mt-2" @click="clearResults('avatar')">
                                Clear
                            </Button>
                        </div>

                        <div v-if="uploadErrors.avatar" class="mt-4 p-4 bg-red-50 rounded-lg">
                            <h4 class="font-medium text-red-800 mb-2">Upload Failed</h4>
                            <p class="text-sm text-red-700">{{ uploadErrors.avatar }}</p>
                            <Button variant="outline" size="sm" class="mt-2" @click="clearResults('avatar')">
                                Clear
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Assignment Upload -->
            <TabsContent value="assignment" class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            Assignment Images
                            <Badge variant="secondary">Multiple Files</Badge>
                        </CardTitle>
                        <CardDescription>
                            Upload multiple images for assignments. Supports various image formats up to 10MB each.
                            Files are stored privately and require authentication to access.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImageUpload
                            context="assignment"
                            :multiple="true"
                            class="mb-4"
                            @upload-success="(files) => handleUploadSuccess('assignment', files)"
                            @upload-error="(error) => handleUploadError('assignment', error)"
                            @config-error="(error) => handleConfigError('assignment', error)"
                        />

                        <!-- Results -->
                        <div v-if="uploadResults.assignment" class="mt-4 p-4 bg-green-50 rounded-lg">
                            <h4 class="font-medium text-green-800 mb-2">Upload Successful!</h4>
                            <div class="space-y-2">
                                <div v-for="file in uploadResults.assignment" :key="file.id" class="text-sm text-green-700">
                                    <p><strong>File:</strong> {{ file.original_name }}</p>
                                    <p><strong>Size:</strong> {{ Math.round(file.size / 1024) }} KB</p>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" class="mt-2" @click="clearResults('assignment')">
                                Clear
                            </Button>
                        </div>

                        <div v-if="uploadErrors.assignment" class="mt-4 p-4 bg-red-50 rounded-lg">
                            <h4 class="font-medium text-red-800 mb-2">Upload Failed</h4>
                            <p class="text-sm text-red-700">{{ uploadErrors.assignment }}</p>
                            <Button variant="outline" size="sm" class="mt-2" @click="clearResults('assignment')">
                                Clear
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- General Upload -->
            <TabsContent value="general" class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            General Images
                            <Badge variant="secondary">Custom Config</Badge>
                        </CardTitle>
                        <CardDescription>
                            General purpose image upload with custom size limits and file type restrictions.
                            Includes SVG support for icons and graphics.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImageUpload
                            context="general"
                            :multiple="true"
                            class="mb-4"
                            @upload-success="(files) => handleUploadSuccess('general', files)"
                            @upload-error="(error) => handleUploadError('general', error)"
                            @config-error="(error) => handleConfigError('general', error)"
                        />

                        <!-- Results -->
                        <div v-if="uploadResults.general" class="mt-4 p-4 bg-green-50 rounded-lg">
                            <h4 class="font-medium text-green-800 mb-2">Upload Successful!</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                <div v-for="file in uploadResults.general" :key="file.id" class="text-sm text-green-700 p-2 bg-white rounded border">
                                    <p><strong>File:</strong> {{ file.original_name }}</p>
                                    <p><strong>Type:</strong> {{ file.mime_type }}</p>
                                    <p><strong>Size:</strong> {{ Math.round(file.size / 1024) }} KB</p>
                                    <a :href="file.url" target="_blank" class="text-blue-600 underline text-xs">View File</a>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" class="mt-2" @click="clearResults('general')">
                                Clear
                            </Button>
                        </div>

                        <div v-if="uploadErrors.general" class="mt-4 p-4 bg-red-50 rounded-lg">
                            <h4 class="font-medium text-red-800 mb-2">Upload Failed</h4>
                            <p class="text-sm text-red-700">{{ uploadErrors.general }}</p>
                            <Button variant="outline" size="sm" class="mt-2" @click="clearResults('general')">
                                Clear
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <!-- Configuration Override Example -->
        <Card>
            <CardHeader>
                <CardTitle>Custom Configuration Override</CardTitle>
                <CardDescription>
                    Example of overriding the default context configuration with custom props.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ImageUpload
                    context="avatar"
                    :max-size="1024 * 1024"
                    :allowed-types="['image/jpeg', 'image/png']"
                    :show-config="false"
                    class="mb-4"
                    @upload-success="(files) => handleUploadSuccess('custom', files)"
                    @upload-error="(error) => handleUploadError('custom', error)"
                />

                <div class="text-sm text-gray-600 mt-2">
                    <p><strong>Custom Settings:</strong></p>
                    <ul class="list-disc list-inside ml-4">
                        <li>Max size: 1MB (overridden from avatar context default)</li>
                        <li>Allowed types: JPEG, PNG only</li>
                        <li>Configuration info hidden</li>
                    </ul>
                </div>

                <!-- Results -->
                <div v-if="uploadResults.custom" class="mt-4 p-4 bg-green-50 rounded-lg">
                    <h4 class="font-medium text-green-800 mb-2">Custom Upload Successful!</h4>
                    <div v-for="file in uploadResults.custom" :key="file.id" class="text-sm text-green-700">
                        <p><strong>File:</strong> {{ file.original_name }}</p>
                        <p><strong>URL:</strong> <a :href="file.url" target="_blank" class="underline">{{ file.url }}</a></p>
                    </div>
                    <Button variant="outline" size="sm" class="mt-2" @click="clearResults('custom')">
                        Clear
                    </Button>
                </div>

                <div v-if="uploadErrors.custom" class="mt-4 p-4 bg-red-50 rounded-lg">
                    <h4 class="font-medium text-red-800 mb-2">Custom Upload Failed</h4>
                    <p class="text-sm text-red-700">{{ uploadErrors.custom }}</p>
                    <Button variant="outline" size="sm" class="mt-2" @click="clearResults('custom')">
                        Clear
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
