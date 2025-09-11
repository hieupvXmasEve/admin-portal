<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Blockquote } from '@tiptap/extension-blockquote';
import { BulletList } from '@tiptap/extension-bullet-list';
import { CodeBlock } from '@tiptap/extension-code-block';
import { Color } from '@tiptap/extension-color';
import { Highlight } from '@tiptap/extension-highlight';
import { HorizontalRule } from '@tiptap/extension-horizontal-rule';
import { Link } from '@tiptap/extension-link';
import { ListItem } from '@tiptap/extension-list-item';
import { OrderedList } from '@tiptap/extension-ordered-list';
import { Table } from '@tiptap/extension-table';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableRow } from '@tiptap/extension-table-row';
import { TextAlign } from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Separator } from "@/components/ui/separator"

import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
    BoldIcon,
    CodeIcon,
    Heading1Icon,
    Heading2Icon,
    Heading3Icon,
    HighlighterIcon,
    ItalicIcon,
    LinkIcon,
    ListIcon,
    ListOrderedIcon,
    MinusIcon,
    QuoteIcon,
    Redo2Icon,
    TableIcon,
    UnderlineIcon,
    Undo2Icon,
} from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';

interface Props {
    modelValue?: string;
    placeholder?: string;
    showToolbar?: boolean;
    minHeight?: string;
    commonVariables?: Array<{ name: string; description: string }>;
    emailMode?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Start typing...',
    showToolbar: true,
    minHeight: '240px',
    emailMode: false,
    commonVariables: () => [
        { name: 'student_name', description: 'Student name' },
        { name: 'student_id', description: 'Student ID' },
        { name: 'student_email', description: 'Student email' },
    ],
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// Tiptap editor setup
const editor = useEditor({
    content: props.modelValue || '',
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
        // Underline,
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
        emit('update:modelValue', editor.getHTML());
    },
});

// Watch for external changes to modelValue
watch(
    () => props.modelValue,
    (val) => {
        if (editor?.value && val !== undefined) {
            const currentEditorContent = editor.value.getHTML();
            // Only update if content is actually different (normalize whitespace)
            const normalizeHtml = (html: string) => html.replace(/\s+/g, ' ').trim();
            if (normalizeHtml(val || '') !== normalizeHtml(currentEditorContent)) {
                editor.value.commands.setContent(val || '');
            }
        }
    },
    { immediate: true },
);

// Toolbar actions
const setLink = () => {
    const url = window.prompt('Enter URL');
    if (!url) return;
    editor?.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const addTable = () => {
    editor?.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
};

const insertVariable = (variable: string) => {
    editor?.value?.chain().focus().insertContent(`{{${variable}}}`).run();
};

// Cleanup on unmount
onBeforeUnmount(() => {
    editor?.value?.destroy();
});
</script>

<template>
    <div class="space-y-2">
        <!-- Rich Text Editor Toolbar -->
        <div v-if="showToolbar" class="space-y-2 rounded-lg border p-3">
            <!-- Row 1: Undo/Redo + Headings -->
            <div class="flex items-center gap-2 h-11 pb-3 overflow-auto">
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().undo().run()" :disabled="!editor?.can().undo()">
                    <Undo2Icon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().redo().run()" :disabled="!editor?.can().redo()">
                    <Redo2Icon class="h-4 w-4" />
                </Button>

                <Separator orientation="vertical" />
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('heading', { level: 1 }) }">
                    <Heading1Icon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('heading', { level: 2 }) }">
                    <Heading2Icon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('heading', { level: 3 }) }">
                    <Heading3Icon class="h-4 w-4" />
                </Button>

                <!-- Row 2: Text Formatting -->

                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleBold().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('bold') }">
                    <BoldIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleItalic().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('italic') }">
                    <ItalicIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleUnderline().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('underline') }">
                    <UnderlineIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleHighlight().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('highlight') }">
                    <HighlighterIcon class="h-4 w-4" />
                </Button>
                <Separator orientation="vertical" />

                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().setTextAlign('left').run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive({ textAlign: 'left' }) }">
                    <AlignLeftIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().setTextAlign('center').run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive({ textAlign: 'center' }) }">
                    <AlignCenterIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().setTextAlign('right').run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive({ textAlign: 'right' }) }">
                    <AlignRightIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().setTextAlign('justify').run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive({ textAlign: 'justify' }) }">
                    <AlignJustifyIcon class="h-4 w-4" />
                </Button>
                <Separator orientation="vertical" />

                <!-- Row 3: Lists + Blocks -->

                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleBulletList().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('bulletList') }">
                    <ListIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleOrderedList().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('orderedList') }">
                    <ListOrderedIcon class="h-4 w-4" />
                </Button>
                <Separator orientation="vertical" />

                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleBlockquote().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('blockquote') }">
                    <QuoteIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().toggleCodeBlock().run()" :class="{ 'bg-primary text-primary-foreground': editor?.isActive('codeBlock') }">
                    <CodeIcon class="h-4 w-4" />
                </Button>
                <Separator orientation="vertical" />

                <Button type="button" size="sm" variant="outline" @click="setLink()">
                    <LinkIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="editor?.chain().focus().setHorizontalRule().run()">
                    <MinusIcon class="h-4 w-4" />
                </Button>
                <Button type="button" size="sm" variant="outline" @click="addTable()">
                    <TableIcon class="h-4 w-4" />
                </Button>
            </div>

            <!-- Quick Variables -->
            <div v-if="commonVariables.length > 0" class="flex items-center gap-2 border-t pt-2 overflow-auto pb-2">
                <span class="shrink-0 text-muted-foreground text-sm whitespace-nowrap">Quick insert:</span>
                <div class="flex gap-1">
                    <Button v-for="variable in commonVariables" :key="variable.name" type="button" size="sm" variant="secondary" @click="insertVariable(variable.name)">
                        {{ variable.description }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Editor Content -->
        <div class="rounded-md border" :style="{ minHeight }">
            <EditorContent 
                :editor="editor" 
                :class="emailMode ? 'email-editor max-w-none p-3' : 'prose max-w-none p-3'" 
            />
        </div>
    </div>
</template>

<style scoped>
:deep(.ProseMirror) {
    outline: none;
    min-height: v-bind(minHeight);
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: #adb5bd;
    pointer-events: none;
    height: 0;
}

/* Email Mode Styling */
:deep(.email-editor .ProseMirror) {
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
}

/* Reset default prose styling for email mode */
:deep(.email-editor .ProseMirror h1) {
    font-size: 24px;
    font-weight: 700;
    margin: 1em 0 0.5em;
    color: #111;
    line-height: 1.3;
}

:deep(.email-editor .ProseMirror h2) {
    font-size: 20px;
    font-weight: 700;
    margin: 1em 0 0.5em;
    color: #111;
    line-height: 1.3;
}

:deep(.email-editor .ProseMirror h3) {
    font-size: 16px;
    font-weight: 700;
    margin: 1em 0 0.5em;
    color: #111;
    line-height: 1.3;
}

:deep(.email-editor .ProseMirror p) {
    margin: 0 0 1em;
    color: #333;
}

/* Lists - sử dụng same styling như EmailContentRenderer - với !important để override Tailwind */
:deep(.email-editor .ProseMirror ul) {
    list-style-type: disc !important;
    margin: 1em 0 !important;
    padding-left: 30px !important;
    list-style-position: outside !important;
    /* Override list-inside from Tailwind */
    list-style: revert !important;
}

:deep(.email-editor .ProseMirror ol) {
    list-style-type: decimal !important;
    margin: 1em 0 !important;
    padding-left: 30px !important;
    list-style-position: outside !important;
    /* Override list-inside from Tailwind */
    list-style: revert !important;
}

:deep(.email-editor .ProseMirror li) {
    margin-bottom: 0.5em !important;
    display: list-item !important;
    list-style-position: outside !important;
}

/* Fix for paragraphs inside list items - critical fix */
:deep(.email-editor .ProseMirror li p) {
    margin: 0 !important;
    display: inline;
}

/* Ensure proper line spacing for list content */
:deep(.email-editor .ProseMirror li > *) {
    display: inline;
}

:deep(.email-editor .ProseMirror li br) {
    display: none;
}

/* Override Tailwind list classes specifically in email editor */
:deep(.email-editor .ProseMirror ul.list-disc.list-inside),
:deep(.email-editor .ProseMirror ol.list-decimal.list-inside) {
    list-style: revert !important;
    list-style-position: outside !important;
    margin: 1em 0 !important;
    padding-left: 30px !important;
}

/* Override Tailwind list reset specifically in email editor */
:deep(.email-editor .ProseMirror ul),
:deep(.email-editor .ProseMirror ol) {
    list-style: revert !important;
    margin: 1em 0 !important;
    padding-left: 30px !important;
}

/* Ensure list items have proper styling */
:deep(.email-editor .ProseMirror ul li) {
    list-style-type: disc;
}

:deep(.email-editor .ProseMirror ol li) {
    list-style-type: decimal;
}

:deep(.email-editor .ProseMirror a) {
    color: #0066cc;
    text-decoration: underline;
}

:deep(.email-editor .ProseMirror a:hover) {
    text-decoration: none;
}

:deep(.email-editor .ProseMirror table) {
    border-collapse: collapse;
    width: 100%;
    margin: 1em 0;
}

:deep(.email-editor .ProseMirror th),
:deep(.email-editor .ProseMirror td) {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

:deep(.email-editor .ProseMirror th) {
    background-color: #f2f2f2;
    font-weight: 700;
}

:deep(.email-editor .ProseMirror blockquote) {
    border-left: 4px solid #ddd;
    padding-left: 1em;
    margin: 1em 0;
    color: #666;
    font-style: italic;
}

:deep(.email-editor .ProseMirror pre),
:deep(.email-editor .ProseMirror code) {
    font-family: 'Courier New', monospace;
    background: #f5f5f5;
    border-radius: 3px;
}

:deep(.email-editor .ProseMirror code) {
    padding: 0.2em 0.4em;
}

:deep(.email-editor .ProseMirror pre) {
    padding: 1em;
    overflow-x: auto;
    border: 1px solid #e0e0e0;
}

:deep(.email-editor .ProseMirror hr) {
    border: none;
    border-top: 1px solid #ddd;
    margin: 1.5em 0;
}

:deep(.email-editor .ProseMirror img) {
    max-width: 100%;
    height: auto;
}

/* Fix for any inline styles that might conflict */
:deep(.email-editor .ProseMirror li p[style*="text-align"]) {
    display: inline !important;
    margin: 0 !important;
    text-align: inherit !important;
}
</style>
