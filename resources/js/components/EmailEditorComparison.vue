<script setup lang="ts">
import EditorContent from '@/components/EditorContent.vue';
import EmailContentRenderer from '@/components/EmailContentRenderer.vue';
import { ref } from 'vue';

const content = ref(`
<h2>Test Lists Comparison</h2>

<h3>Bullet List:</h3>
<ul>
  <li>First item</li>
  <li>Second item with <strong>bold text</strong></li>
  <li>Third item</li>
</ul>

<h3>Numbered List:</h3>
<ol>
  <li>Step one</li>
  <li>Step two with <em>italic text</em></li>
  <li>Step three</li>
</ol>

<p>Regular paragraph after lists.</p>
`);

const testVariables = [
  { name: 'student_name', description: 'Student Name' },
  { name: 'student_id', description: 'Student ID' },
];
</script>

<template>
  <div class="p-6 space-y-8">
    <h1 class="text-2xl font-bold">Email Editor vs Content Renderer Comparison</h1>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- EditorContent in Email Mode -->
      <div class="space-y-4">
        <h2 class="text-xl font-semibold text-green-700">EditorContent (Email Mode)</h2>
        <div class="border-2 border-green-300 rounded-lg">
          <EditorContent
            v-model="content"
            :common-variables="testVariables"
            :email-mode="true"
            min-height="300px"
            placeholder="Edit content here..."
          />
        </div>
      </div>
      
      <!-- EmailContentRenderer -->
      <div class="space-y-4">
        <h2 class="text-xl font-semibold text-blue-700">EmailContentRenderer (Preview)</h2>
        <div class="border-2 border-blue-300 rounded-lg min-h-[300px]">
          <div class="p-3">
            <EmailContentRenderer :content="content" />
          </div>
        </div>
      </div>
    </div>
    
    <!-- Raw HTML Output -->
    <div class="mt-8 p-4 bg-gray-100 rounded-lg">
      <h3 class="font-semibold mb-2">Raw HTML:</h3>
      <pre class="text-sm bg-white p-3 rounded border overflow-auto">{{ content }}</pre>
    </div>
    
    <!-- Instructions -->
    <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
      <h3 class="font-semibold text-yellow-800 mb-2">Test Instructions:</h3>
      <ul class="text-sm text-yellow-700 space-y-1">
        <li>• Edit the content in the left editor</li>
        <li>• Add lists using the toolbar buttons</li>
        <li>• Compare the styling between editor and preview</li>
        <li>• Lists should look identical in both panels</li>
        <li>• List items should be on single lines (not broken into multiple lines)</li>
      </ul>
    </div>
  </div>
</template>
