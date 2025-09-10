<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useEmailTemplate } from '@/composables/useEmailTemplate'
import { toTypedSchema } from '@vee-validate/zod'
import * as z from 'zod'
import { useForm } from 'vee-validate'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage
} from '@/components/ui/form'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { router } from '@inertiajs/vue3'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { Link } from '@tiptap/extension-link'
import { Underline } from '@tiptap/extension-underline'
import { Color } from '@tiptap/extension-color'
import { TextStyle } from '@tiptap/extension-text-style'
import { TextAlign } from '@tiptap/extension-text-align'
import { BulletList } from '@tiptap/extension-bullet-list'
import { OrderedList } from '@tiptap/extension-ordered-list'
import { ListItem } from '@tiptap/extension-list-item'
import { Blockquote } from '@tiptap/extension-blockquote'
import { CodeBlock } from '@tiptap/extension-code-block'
import { HorizontalRule } from '@tiptap/extension-horizontal-rule'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableHeader } from '@tiptap/extension-table-header'
import { TableCell } from '@tiptap/extension-table-cell'
import { Highlight } from '@tiptap/extension-highlight'
import { 
  EyeIcon, BoldIcon, ItalicIcon, UnderlineIcon, LinkIcon, 
  Heading1Icon, Heading2Icon, Heading3Icon,
  ListIcon, ListOrderedIcon, QuoteIcon, CodeIcon, 
  AlignLeftIcon, AlignCenterIcon, AlignRightIcon, AlignJustifyIcon,
  MinusIcon, TableIcon, HighlighterIcon, PaletteIcon,
  Undo2Icon, Redo2Icon
} from 'lucide-vue-next'

interface Props {
  template: any
  templateTypes: Record<string, string>
}

const props = defineProps<Props>()
const { updateTemplate } = useEmailTemplate()

// Validation schema
const formSchema = toTypedSchema(z.object({
  name: z.string().min(1, 'Template name is required'),
  type: z.string().min(1, 'Template type is required'),
  subject: z.string().min(1, 'Subject is required'),
  html_content: z.string().min(1, 'Content is required'),
  text_content: z.string().optional(),
  description: z.string().optional(),
  is_active: z.boolean().default(true),
}))

const { handleSubmit, setFieldValue, setValues, values } = useForm({
  validationSchema: formSchema,
  // Preserve field values even when their components unmount (e.g., when switching tabs)
  keepValuesOnUnmount: true,
})

const activeTab = ref('content')
const isSubmitting = ref(false)
const isGeneratingPreview = ref(false)
const previewData = ref<any>(null)
const sampleVariables = ref<Record<string, string>>({})

// Extract variables from subject + html + text
const extractedVariables = computed(() => {
  const content = (values.subject || '') + ' ' + (values.html_content || '') + ' ' + (values.text_content || '')
  const matches = content.match(/\{\{([^}]+)\}\}/g)
  if (!matches) return []
  return [...new Set(matches.map(m => m.slice(2, -2).trim()))]
})

watch(extractedVariables, (vars) => {
  const samples: Record<string, string> = {}
  vars.forEach(v => { samples[v] = sampleVariables.value[v] || `[${v}]` })
  sampleVariables.value = samples
})

// Tiptap editor for HTML content
const editor = useEditor({
  // Initialize from current form value to keep editor and form in sync
  content: () => values.html_content || '',
  extensions: [
    StarterKit.configure({
      // Disable default extensions that we'll configure separately
      link: false,
      bulletList: false,
      orderedList: false,
      listItem: false,
      blockquote: false,
      codeBlock: false,
      horizontalRule: false,
    }),
    // Text formatting
    TextStyle,
    Color,
    Underline,
    Highlight.configure({
      multicolor: true,
    }),
    // Links
    Link.configure({ 
      openOnClick: false,
      HTMLAttributes: {
        class: 'text-blue-600 underline hover:text-blue-800',
      },
    }),
    // Text alignment
    TextAlign.configure({
      types: ['heading', 'paragraph'],
      alignments: ['left', 'center', 'right', 'justify'],
      defaultAlignment: 'left',
    }),
    // Lists
    ListItem,
    BulletList.configure({
      HTMLAttributes: {
        class: 'list-disc list-inside',
      },
    }),
    OrderedList.configure({
      HTMLAttributes: {
        class: 'list-decimal list-inside',
      },
    }),
    // Block elements
    Blockquote.configure({
      HTMLAttributes: {
        class: 'border-l-4 border-gray-300 pl-4 italic',
      },
    }),
    CodeBlock.configure({
      HTMLAttributes: {
        class: 'bg-gray-100 text-gray-800 font-mono p-3 rounded',
      },
    }),
    HorizontalRule.configure({
      HTMLAttributes: {
        class: 'my-4 border-gray-300',
      },
    }),
    // Tables
    Table.configure({
      resizable: true,
      HTMLAttributes: {
        class: 'border-collapse table-auto w-full border border-gray-300',
      },
    }),
    TableRow,
    TableHeader.configure({
      HTMLAttributes: {
        class: 'border border-gray-300 px-4 py-2 text-left bg-gray-50 font-semibold',
      },
    }),
    TableCell.configure({
      HTMLAttributes: {
        class: 'border border-gray-300 px-4 py-2',
      },
    }),
  ],
  onUpdate: ({ editor }) => {
    setFieldValue('html_content', editor.getHTML())
  },
})

watch(() => values.html_content, (val) => {
  // Sync form changes back to editor, with better HTML comparison
  if (editor?.value && val !== undefined) {
    const currentEditorContent = editor.value.getHTML()
    // Only update if content is actually different (normalize whitespace)
    const normalizeHtml = (html: string) => html.replace(/\s+/g, ' ').trim()
    if (normalizeHtml(val || '') !== normalizeHtml(currentEditorContent)) {
      editor.value.commands.setContent(val || '', false)
    }
  }
}, { immediate: true })

// Initialize form with template data
onMounted(() => {
  if (props.template) {
    setValues({
      name: props.template.name || '',
      type: props.template.type || '',
      subject: props.template.subject || '',
      html_content: props.template.html_content || '',
      text_content: props.template.text_content || '',
      description: props.template.description || '',
      is_active: props.template.is_active ?? true,
    })

    // Set editor content - the watcher will handle syncing
    if (editor?.value) {
      editor.value.commands.setContent(props.template.html_content || '', false)
    }

    // Initialize sample variables
    if (props.template.variables) {
      const samples: Record<string, string> = {}
      props.template.variables.forEach((variable: string) => {
        samples[variable] = `[${variable}]`
      })
      sampleVariables.value = samples
    }
  }
})

const setLink = () => {
  const url = window.prompt('Enter URL')
  if (!url) return
  editor?.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const addTable = () => {
  editor?.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}

const insertVariable = (variable: string) => {
  editor?.value?.chain().focus().insertContent(`{{${variable}}}`).run()
}

// Auto-generate preview when switching to the preview tab
watch(activeTab, (tab) => {
  if (tab === 'preview') {
    generatePreview()
  }
})

const commonVariables = [
  { name: 'student_name', description: 'Student name' },
  { name: 'student_id', description: 'Student ID' },
  { name: 'course_name', description: 'Course name' },
  { name: 'semester', description: 'Current semester' },
]

const generatePreview = async () => {
  if (!values.html_content) return
  isGeneratingPreview.value = true
  try {
    const variables = { ...sampleVariables.value }
    let subject = values.subject || ''
    let html = values.html_content || ''
    let text = values.text_content || ''

    Object.entries(variables).forEach(([key, value]) => {
      const placeholder = `{{${key}}}`
      const esc = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
      subject = subject.replace(new RegExp(esc(placeholder), 'g'), String(value))
      html = html.replace(new RegExp(esc(placeholder), 'g'), String(value))
      text = text.replace(new RegExp(esc(placeholder), 'g'), String(value))
    })

    previewData.value = { subject, html, text: text || null }
  } finally {
    isGeneratingPreview.value = false
  }
}

const onSubmit = handleSubmit(async (formValues) => {
  isSubmitting.value = true
  try {
    await updateTemplate(props.template.id, formValues)
    router.visit('/systems/email-templates')
  } catch (error) {
    // Error handling is done in the composable via toast
    // Just prevent navigation if there's an error
    console.error('Template update failed:', error)
  } finally {
    isSubmitting.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Edit Email Template</h1>
      <p class="text-muted-foreground">Update your email template content.</p>
    </div>

    <form @submit.prevent="onSubmit" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <FormField name="name" v-slot="{ componentField }">
          <FormItem>
            <FormLabel>Template Name *</FormLabel>
            <FormControl>
              <Input v-bind="componentField" placeholder="e.g., Welcome Email" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>

        <FormField name="type" v-slot="{ componentField }">
          <FormItem>
            <FormLabel>Template Type *</FormLabel>
            <Select v-bind="componentField">
              <FormControl>
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem v-for="(label, value) in templateTypes" :key="value" :value="value">{{ label }}</SelectItem>
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        </FormField>

        <FormField name="description" v-slot="{ componentField }" class="sm:col-span-2">
          <FormItem>
            <FormLabel>Description</FormLabel>
            <FormControl>
              <Textarea v-bind="componentField" rows="2" placeholder="Brief description" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>
      </div>

      <FormField name="subject" v-slot="{ componentField }">
        <FormItem>
          <FormLabel>Subject *</FormLabel>
          <FormControl>
            <Input v-bind="componentField" placeholder="Subject (use {{variable}} for dynamic content)" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <Tabs v-model="activeTab">
        <TabsList class="grid grid-cols-3 w-full">
          <TabsTrigger value="content">Content</TabsTrigger>
          <TabsTrigger value="preview">Preview</TabsTrigger>
          <TabsTrigger value="variables">Variables ({{ extractedVariables.length }})</TabsTrigger>
        </TabsList>

        <TabsContent value="content" class="space-y-2">
          <!-- Rich Text Editor Toolbar -->
          <div class="border rounded-lg p-3 space-y-3">
            <!-- Row 1: Undo/Redo + Headings -->
            <div class="flex items-center gap-2">
              <div class="flex gap-1 border-r pr-2">
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().undo().run()" :disabled="!editor?.value?.can().undo()">
                  <Undo2Icon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().redo().run()" :disabled="!editor?.value?.can().redo()">
                  <Redo2Icon class="h-4 w-4" />
                </Button>
              </div>
              <div class="flex gap-1 border-r pr-2">
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleHeading({ level: 1 }).run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('heading', { level: 1 }) }">
                  <Heading1Icon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('heading', { level: 2 }) }">
                  <Heading2Icon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('heading', { level: 3 }) }">
                  <Heading3Icon class="h-4 w-4" />
                </Button>
              </div>
            </div>
            
            <!-- Row 2: Text Formatting -->
            <div class="flex items-center gap-2">
              <div class="flex gap-1 border-r pr-2">
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleBold().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('bold') }">
                  <BoldIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleItalic().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('italic') }">
                  <ItalicIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleUnderline().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('underline') }">
                  <UnderlineIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleHighlight().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('highlight') }">
                  <HighlighterIcon class="h-4 w-4" />
                </Button>
              </div>
              <div class="flex gap-1 border-r pr-2">
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().setTextAlign('left').run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive({ textAlign: 'left' }) }">
                  <AlignLeftIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().setTextAlign('center').run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive({ textAlign: 'center' }) }">
                  <AlignCenterIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().setTextAlign('right').run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive({ textAlign: 'right' }) }">
                  <AlignRightIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().setTextAlign('justify').run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive({ textAlign: 'justify' }) }">
                  <AlignJustifyIcon class="h-4 w-4" />
                </Button>
              </div>
            </div>
            
            <!-- Row 3: Lists + Blocks -->
            <div class="flex items-center gap-2">
              <div class="flex gap-1 border-r pr-2">
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleBulletList().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('bulletList') }">
                  <ListIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleOrderedList().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('orderedList') }">
                  <ListOrderedIcon class="h-4 w-4" />
                </Button>
              </div>
              <div class="flex gap-1 border-r pr-2">
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleBlockquote().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('blockquote') }">
                  <QuoteIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().toggleCodeBlock().run()" :class="{ 'bg-primary text-primary-foreground': editor?.value?.isActive('codeBlock') }">
                  <CodeIcon class="h-4 w-4" />
                </Button>
              </div>
              <div class="flex gap-1">
                <Button type="button" size="sm" variant="outline" @click="setLink()">
                  <LinkIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.value?.chain().focus().setHorizontalRule().run()">
                  <MinusIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="addTable()">
                  <TableIcon class="h-4 w-4" />
                </Button>
              </div>
            </div>
            
            <!-- Quick Variables -->
            <div class="flex items-center gap-2 pt-2 border-t">
              <span class="text-sm text-muted-foreground">Quick insert:</span>
              <div class="flex gap-1">
                <Button type="button" size="sm" variant="secondary" @click="insertVariable('student_name')">
                  Student Name
                </Button>
                <Button type="button" size="sm" variant="secondary" @click="insertVariable('course_name')">
                  Course Name
                </Button>
                <Button type="button" size="sm" variant="secondary" @click="insertVariable('semester')">
                  Semester
                </Button>
              </div>
            </div>
          </div>
          <FormField name="html_content" v-slot="{ componentField }">
            <FormItem>
              <div class="border rounded-md min-h-[240px]">
                <EditorContent :editor="editor" class="prose max-w-none p-3" />
              </div>
              <FormMessage />
            </FormItem>
          </FormField>
        </TabsContent>

        <TabsContent value="preview" class="space-y-4">
          <div class="flex justify-end">
            <Button type="button" size="sm" variant="outline" @click="generatePreview" :disabled="isGeneratingPreview">
              <EyeIcon class="h-4 w-4 mr-2" /> {{ isGeneratingPreview ? 'Generating...' : 'Generate Preview' }}
            </Button>
          </div>
          <div v-if="previewData" class="space-y-4">
            <div>
              <div class="text-xs font-medium mb-1">Subject:</div>
              <div class="border rounded p-2">{{ previewData.subject }}</div>
            </div>
            <div>
              <div class="text-xs font-medium mb-1">HTML Content:</div>
              <div class="border rounded p-3" v-html="previewData.html"></div>
            </div>
            <div v-if="previewData.text">
              <div class="text-xs font-medium mb-1">Plain Text:</div>
              <div class="border rounded p-2 whitespace-pre-wrap">{{ previewData.text }}</div>
            </div>
          </div>
          <div v-else class="text-sm text-muted-foreground">Click Generate Preview to simulate variables.</div>
        </TabsContent>

        <TabsContent value="variables" class="space-y-4">
          <div class="bg-primary/5 rounded-lg p-4">
            <h4 class="text-sm font-medium mb-2">Detected Variables</h4>
            <div v-if="extractedVariables.length" class="space-y-2">
              <div v-for="v in extractedVariables" :key="v" class="flex items-center gap-2">
                <code class="text-primary text-sm">{{ v }}</code>
                <Input v-model="sampleVariables[v]" class="flex-1" :placeholder="`Sample for ${v}`" />
              </div>
            </div>
            <div v-else class="text-sm text-muted-foreground">No variables detected. Use {{variable_name}} syntax in subject or content.</div>
          </div>

          <div class="bg-muted/50 rounded-lg p-4">
            <h4 class="text-sm font-medium mb-2">Common Variables</h4>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div v-for="cv in commonVariables" :key="cv.name" class="flex items-center justify-between">
                <code class="text-muted-foreground">{{ cv.name }}</code>
                <span class="text-muted-foreground">{{ cv.description }}</span>
              </div>
            </div>
          </div>
        </TabsContent>
      </Tabs>

      <FormField name="text_content" v-slot="{ componentField }">
        <FormItem>
          <FormLabel>Plain Text (optional)</FormLabel>
          <FormControl>
            <Textarea v-bind="componentField" rows="5" placeholder="Text-only version" />
          </FormControl>
          <FormDescription>Plain text is recommended for maximum email client compatibility.</FormDescription>
          <FormMessage />
        </FormItem>
      </FormField>

      <FormField name="is_active" v-slot="{ componentField }">
        <FormItem class="flex flex-row items-start space-x-3 space-y-0">
          <FormControl>
            <Checkbox v-bind="componentField" />
          </FormControl>
          <div class="space-y-1 leading-none">
            <FormLabel>Set as active template</FormLabel>
          </div>
        </FormItem>
      </FormField>

      <div class="flex justify-end gap-2">
        <Button type="button" variant="outline" @click="router.visit('/systems/email-templates')">Cancel</Button>
        <Button type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Saving...' : 'Update Template' }}</Button>
      </div>
    </form>
  </div>
</template>
