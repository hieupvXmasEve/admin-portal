---
trigger: always_on
---

# Shadcn-Vue Component Conventions

This document outlines the standard patterns and best practices for using Shadcn-Vue components in this project to avoid common errors and ensure consistency.

## 1. Select Component

### 1.1. Value Prop Requirements

- **Constraint**: Every `<SelectItem />` **MUST** have a `value` prop that is a **non-empty string**.
- **Error**: `Uncaught (in promise) Error: A <SelectItem /> must have a value prop that is not an empty string. This is because the Select value can be set to an empty string to clear the selection and show the placeholder.`
- **Reason**: Reka-ui (Radix Vue) uses empty strings to signify a "cleared" state or to show the placeholder.
- **Solution**:
    - Use a descriptive string like `"all"`, `"none"`, or `"default"` for options that represent a reset or global state.
    - **Never** use `value=""`.
    - Always cast numeric IDs to strings using `String(id)`.
    - Filter out any potential empty values from arrays before iterating with `v-for`.

### 1.2. Usage Pattern

```vue
<Select v-model="selectedValue">
  <SelectTrigger>
    <SelectValue placeholder="Select an option" />
  </SelectTrigger>
  <SelectContent>
    <!-- Use "all" instead of "" for reset options -->
    <SelectItem value="all">All Items</SelectItem>
    <SelectItem 
      v-for="item in items.filter(i => i.id)" 
      :key="item.id" 
      :value="String(item.id)"
    >
      {{ item.name }}
    </SelectItem>
  </SelectContent>
</Select>
```

## 2. Form & Input Components

### 2.1. V-Model Usage

- Prefer derived state or `ref` for simple filters.
- Use `useForm` from Inertia for complex data submissions.
- Ensure `v-model` is always bound to a value that matches one of the `SelectItem` values or is `undefined`/`null` to show the placeholder.

### 2.2. Validation

- Use `vee-validate` or Inertia's built-in validation.
- Display errors using consistent UI components (e.g., `form.errors.field_name`).

## 3. General Best Practices

### 3.1. Hydration & Props

- Always provide default values for props used in Shadcn components to avoid initialization errors during hydration.
- Example: `const selected = ref(props.initialValue || 'all')`.

### 3.2. Types

- Define strict interfaces for data items used in selection components.
- Ensure `SelectItem` values are consistent with the data type expected by the backend.
