<script setup lang="ts">
interface Props {
    content: string;
}

defineProps<Props>();
</script>

<template>
    <div class="email-content-container">
        <div class="email-content" v-html="content"></div>
    </div>
</template>

<style>
/* Phần này quan trọng: sử dụng global styles nhưng cô lập trong container */
.email-content-container {
    /* Container để cô lập styles */
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
}

/* Reset và cô lập khỏi Tailwind */
.email-content {
    /* Reset all styles */
    all: revert;

    /* Đảm bảo widths đúng */
    width: 100%;
    max-width: 100%;

    /* Base text styling */
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
}

/* Headers */
.email-content h1 {
    font-size: 24px;
    font-weight: bold;
    margin: 1em 0 0.5em;
    color: #111;
}

.email-content h2 {
    font-size: 20px;
    font-weight: bold;
    margin: 1em 0 0.5em;
    color: #111;
}

.email-content h3 {
    font-size: 16px;
    font-weight: bold;
    margin: 1em 0 0.5em;
    color: #111;
}

/* Paragraphs */
.email-content p {
    margin: 0 0 1em;
}

/* Lists */
.email-content ul {
    list-style-type: disc;
    margin: 1em 0;
    padding-left: 30px;
    list-style-position: outside;
}

.email-content ol {
    list-style-type: decimal;
    margin: 1em 0;
    padding-left: 30px;
    list-style-position: outside;
}

.email-content li {
    margin-bottom: 0.5em;
    display: list-item;
}

/* Fix for nested paragraphs in list items */
.email-content li p {
    margin: 0 !important;
    display: inline;
}

/* Ensure proper line spacing for list content */
.email-content li > * {
    display: inline;
}

.email-content li br {
    display: none;
}

/* Links */
.email-content a {
    color: #0066cc;
    text-decoration: underline;
}

.email-content a:hover {
    text-decoration: none;
}

/* Tables */
.email-content table {
    border-collapse: collapse;
    width: 100%;
    margin: 1em 0;
}

.email-content th,
.email-content td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.email-content th {
    background-color: #f2f2f2;
    font-weight: bold;
}

/* Blockquotes */
.email-content blockquote {
    border-left: 4px solid #ddd;
    padding-left: 1em;
    margin: 1em 0;
    color: #666;
}

/* Code blocks */
.email-content pre,
.email-content code {
    font-family: monospace;
    background: #f5f5f5;
    padding: 0.2em 0.4em;
    border-radius: 3px;
}

.email-content pre {
    padding: 1em;
    overflow-x: auto;
}

/* Horizontal rule */
.email-content hr {
    border: none;
    border-top: 1px solid #ddd;
    margin: 1.5em 0;
}

/* Images */
.email-content img {
    max-width: 100%;
    height: auto;
}
</style>
