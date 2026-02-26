# Email Variables System

## Overview

The email variables system provides a unified approach to handling dynamic content in email templates, particularly for bulk emails sent to students.

## Types

### `BulkEmailStudent`
```typescript
interface BulkEmailStudent {
  id: number;
  student_id: string;
  fullname: string;
  email: string;
  curriculum_version_code?: string;
}
```

### `EmailVariable`
```typescript
interface EmailVariable {
  name: string;
  description: string;
  value?: string;
}
```

## Available Variables

### Student-specific Variables
- `student_name`: Student's full name (derived from `fullname`)
- `student_id`: Student ID
- `student_email`: Student's email address (derived from `email`)
- `curriculum_version_code`: Curriculum version code

### General Variables
- `course_name`: Course name
- `due_date`: Due date
- `semester`: Current semester
- `academic_year`: Academic year
- `instructor_name`: Instructor name

## Components

### `EmailEditor`
Enhanced rich text editor with email-specific styling and preview functionality.

**Props:**
- `commonVariables`: Array of available variables
- `students`: Optional array of student data for preview
- `showPreview`: Enable/disable preview tab

**Usage:**
```vue
<EmailEditor
  v-model="content"
  :common-variables="STUDENT_EMAIL_VARIABLES"
  :students="selectedStudents"
  :show-preview="true"
/>
```

### `EmailContentRenderer`
Renders email content with proper styling, isolated from global CSS.

**Props:**
- `content`: HTML content to render

**Usage:**
```vue
<EmailContentRenderer :content="processedContent" />
```

## Composables

### `useStudentEmailVariables`
Manages student-related email variables.

**Usage:**
```typescript
const { 
  buildPerRecipientVariables,
  buildGlobalVariables,
  getSampleData 
} = useStudentEmailVariables(selectedStudents);
```

## Functions

### `extractStudentVariables(student: BulkEmailStudent)`
Extracts all available variables from a student object.

### `getStudentSampleData(students: BulkEmailStudent[])`
Returns sample data for preview purposes.

## Usage Examples

### Bulk Email with Student Data
```vue
<script setup>
import { STUDENT_EMAIL_VARIABLES } from '@/types/email';
import { useStudentEmailVariables } from '@/composables/useStudentEmailVariables';

const selectedStudents = ref<BulkEmailStudent[]>([]);
const { buildPerRecipientVariables } = useStudentEmailVariables(selectedStudents);

// Build variables when template changes
const onTemplateChange = () => {
  if (selectedStudents.value.length > 0) {
    form.templateVariablesPerRecipient = buildPerRecipientVariables();
  }
};
</script>

<template>
  <EmailEditor
    v-model="form.content"
    :common-variables="STUDENT_EMAIL_VARIABLES"
    :students="selectedStudents"
    :show-preview="true"
  />
</template>
```

### Email Template Creation
```vue
<script setup>
import { DEFAULT_EMAIL_VARIABLES } from '@/types/email';

const commonVariables = DEFAULT_EMAIL_VARIABLES;
</script>

<template>
  <EmailEditor
    v-model="content"
    :common-variables="commonVariables"
    :show-preview="true"
  />
</template>
```

## CSS Isolation

The email editor uses CSS isolation techniques to ensure email content displays correctly:

1. **Email Mode**: Special CSS mode that overrides Tailwind defaults with `!important`
2. **Scoped Styles**: Component-specific styles that don't leak
3. **Content Renderer**: Isolated rendering component with `all: revert`
4. **List Fixes**: Specific CSS rules to fix `<li><p>` structure issues

### Key CSS Fixes for Lists:
- `list-style-position: outside !important` - Ensures proper bullet/number positioning
- `li p { display: inline !important; margin: 0 !important }` - Prevents line breaks in list items
- Direct override of Tailwind's `list-inside` classes

## Variable Replacement

Variables use the format `{{variable_name}}` and are replaced during:
- **Preview**: With sample data or actual student data
- **Sending**: With per-recipient actual values

Example:
```html
Hello {{student_name}},

Your assignment for {{course_name}} is due on {{due_date}}.

Best regards,
{{campus_name}} Team
```

## Best Practices

1. **Always use types**: Import and use `BulkEmailStudent` and `EmailVariable` types
2. **Consistent variable names**: Use the predefined constants from `@/types/email`
3. **Preview functionality**: Always enable preview for better user experience
4. **Student data**: Pass student data to components for accurate previews
5. **Error handling**: Provide fallbacks for missing variables
