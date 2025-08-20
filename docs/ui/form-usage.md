# Form Usage Guide - Shadcn Vue với Vee-Validate

Hướng dẫn sử dụng các components Form của shadcn-vue với vee-validate và Zod validation trong dự án.

## Cài đặt và Import

### Dependencies cần thiết

```bash
npm install @vee-validate/zod vee-validate zod
```

### Import các components

```typescript
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import * as z from 'zod';

// Shadcn-vue Form components
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';

// Other UI components
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
```

## Cấu trúc cơ bản của Form

### 1. Tạo Schema với Zod

```typescript
const formSchema = toTypedSchema(z.object({
  username: z.string().min(2, 'Username phải có ít nhất 2 ký tự').max(50),
  email: z.string().email('Email không hợp lệ'),
  age: z.number().min(18, 'Tuổi phải từ 18 trở lên'),
  terms: z.boolean().refine(val => val, 'Bạn phải đồng ý với điều khoản'),
  interests: z.array(z.string()).min(1, 'Chọn ít nhất một sở thích'),
}));
```

### 2. Setup Form với useForm

```typescript
const { handleSubmit, setFieldValue, values } = useForm({
  validationSchema: formSchema,
  initialValues: {
    username: '',
    email: '',
    age: 18,
    terms: false,
    interests: [],
  },
});
```

### 3. Xử lý Submit

```typescript
const onSubmit = handleSubmit((values) => {
  console.log('Form data:', values);
  // Xử lý logic submit ở đây
});
```

## Các loại Input Fields

### 1. Text Input

```vue
<FormField v-slot="{ componentField }" name="username">
  <FormItem>
    <FormLabel>Tên người dùng</FormLabel>
    <FormControl>
      <Input 
        v-bind="componentField" 
        placeholder="Nhập tên người dùng" 
        type="text" 
      />
    </FormControl>
    <FormDescription>
      Tên người dùng sẽ hiển thị công khai
    </FormDescription>
    <FormMessage />
  </FormItem>
</FormField>
```

### 2. Email Input

```vue
<FormField v-slot="{ componentField }" name="email">
  <FormItem>
    <FormLabel>Email</FormLabel>
    <FormControl>
      <Input 
        v-bind="componentField" 
        placeholder="example@email.com" 
        type="email" 
      />
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>
```

### 3. Number Input

```vue
<FormField v-slot="{ componentField }" name="age">
  <FormItem>
    <FormLabel>Tuổi</FormLabel>
    <FormControl>
      <Input 
        v-bind="componentField" 
        placeholder="18" 
        type="number" 
        min="18"
        max="100"
      />
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>
```

### 4. Date Input

```vue
<FormField v-slot="{ componentField }" name="birthDate">
  <FormItem>
    <FormLabel>Ngày sinh</FormLabel>
    <FormControl>
      <Input 
        v-bind="componentField" 
        type="date" 
      />
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>
```

### 5. Select Dropdown

```vue
<FormField v-slot="{ componentField }" name="country">
  <FormItem>
    <FormLabel>Quốc gia</FormLabel>
    <FormControl>
      <Select v-bind="componentField">
        <SelectTrigger>
          <SelectValue placeholder="Chọn quốc gia" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="vn">Việt Nam</SelectItem>
          <SelectItem value="us">Hoa Kỳ</SelectItem>
          <SelectItem value="jp">Nhật Bản</SelectItem>
        </SelectContent>
      </Select>
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>
```

## Checkbox - Single

### Schema
```typescript
const schema = z.object({
  acceptTerms: z.boolean().refine(val => val, 'Bạn phải đồng ý với điều khoản'),
});
```

### Component
```vue
<FormField v-slot="{ value, handleChange }" name="acceptTerms">
  <FormItem class="flex flex-row items-start space-x-3 space-y-0">
    <FormControl>
      <Checkbox
        :model-value="value"
        @update:model-value="handleChange"
      />
    </FormControl>
    <FormLabel class="font-normal">
      Tôi đồng ý với điều khoản và điều kiện
    </FormLabel>
    <FormMessage />
  </FormItem>
</FormField>
```

## Checkbox - Multiple (Array)

### Schema
```typescript
const schema = z.object({
  interests: z.array(z.string()).min(1, 'Chọn ít nhất một sở thích'),
});
```

### Form setup
```typescript
const interests = [
  { id: 'programming', label: 'Lập trình' },
  { id: 'design', label: 'Thiết kế' },
  { id: 'music', label: 'Âm nhạc' },
  { id: 'sports', label: 'Thể thao' },
];

const { handleSubmit } = useForm({
  validationSchema: formSchema,
  initialValues: {
    interests: [], // Mảng rỗng ban đầu
  },
});
```

### Component
```vue
<FormField name="interests">
  <FormItem>
    <FormLabel class="text-base">Sở thích</FormLabel>
    <FormDescription>
      Chọn các sở thích của bạn
    </FormDescription>

    <FormField 
      v-for="interest in interests" 
      v-slot="{ value, handleChange }" 
      :key="interest.id" 
      type="checkbox" 
      :value="interest.id" 
      :unchecked-value="false" 
      name="interests"
    >
      <FormItem class="flex flex-row items-start space-x-3 space-y-0">
        <FormControl>
          <Checkbox
            :model-value="value.includes(interest.id)"
            @update:model-value="handleChange"
          />
        </FormControl>
        <FormLabel class="font-normal">
          {{ interest.label }}
        </FormLabel>
      </FormItem>
    </FormField>
    
    <FormMessage />
  </FormItem>
</FormField>
```

## Radio Group

### Schema
```typescript
const schema = z.object({
  gender: z.enum(['male', 'female', 'other'], {
    message: 'Vui lòng chọn giới tính',
  }),
});
```

### Component
```vue
<FormField v-slot="{ value, handleChange }" name="gender">
  <FormItem>
    <FormLabel>Giới tính</FormLabel>
    <FormControl>
      <RadioGroup 
        :model-value="value" 
        @update:model-value="handleChange" 
        class="flex flex-col space-y-2"
      >
        <div class="flex items-center space-x-2">
          <RadioGroupItem id="male" value="male" />
          <label for="male">Nam</label>
        </div>
        <div class="flex items-center space-x-2">
          <RadioGroupItem id="female" value="female" />
          <label for="female">Nữ</label>
        </div>
        <div class="flex items-center space-x-2">
          <RadioGroupItem id="other" value="other" />
          <label for="other">Khác</label>
        </div>
      </RadioGroup>
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>
```

## Textarea

```vue
<FormField v-slot="{ componentField }" name="description">
  <FormItem>
    <FormLabel>Mô tả</FormLabel>
    <FormControl>
      <Textarea 
        v-bind="componentField"
        placeholder="Nhập mô tả..."
        rows="4"
      />
    </FormControl>
    <FormDescription>
      Mô tả chi tiết về bản thân (tối đa 500 ký tự)
    </FormDescription>
    <FormMessage />
  </FormItem>
</FormField>
```

## Form hoàn chỉnh - Ví dụ tổng hợp

```vue
<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { h } from 'vue';
import * as z from 'zod';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { toast } from '@/components/ui/toast';

const interests = [
  { id: 'programming', label: 'Lập trình' },
  { id: 'design', label: 'Thiết kế' },
  { id: 'music', label: 'Âm nhạc' },
  { id: 'sports', label: 'Thể thao' },
];

const formSchema = toTypedSchema(z.object({
  username: z.string().min(2, 'Username phải có ít nhất 2 ký tự').max(50),
  email: z.string().email('Email không hợp lệ'),
  age: z.number().min(18, 'Tuổi phải từ 18 trở lên'),
  country: z.string().min(1, 'Vui lòng chọn quốc gia'),
  gender: z.enum(['male', 'female', 'other'], {
    message: 'Vui lòng chọn giới tính',
  }),
  interests: z.array(z.string()).min(1, 'Chọn ít nhất một sở thích'),
  acceptTerms: z.boolean().refine(val => val, 'Bạn phải đồng ý với điều khoản'),
  description: z.string().max(500, 'Mô tả không được quá 500 ký tự').optional(),
}));

const { handleSubmit } = useForm({
  validationSchema: formSchema,
  initialValues: {
    username: '',
    email: '',
    age: 18,
    country: '',
    gender: '',
    interests: [],
    acceptTerms: false,
    description: '',
  },
});

const onSubmit = handleSubmit((values) => {
  toast({
    title: 'Form đã được gửi!',
    description: h('pre', { 
      class: 'mt-2 w-[340px] rounded-md bg-slate-950 p-4' 
    }, h('code', { 
      class: 'text-white' 
    }, JSON.stringify(values, null, 2))),
  });
});
</script>

<template>
  <form @submit="onSubmit" class="space-y-6">
    <!-- Username -->
    <FormField v-slot="{ componentField }" name="username">
      <FormItem>
        <FormLabel>Tên người dùng</FormLabel>
        <FormControl>
          <Input v-bind="componentField" placeholder="Nhập tên người dùng" />
        </FormControl>
        <FormDescription>
          Tên người dùng sẽ hiển thị công khai
        </FormDescription>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Email -->
    <FormField v-slot="{ componentField }" name="email">
      <FormItem>
        <FormLabel>Email</FormLabel>
        <FormControl>
          <Input v-bind="componentField" placeholder="example@email.com" type="email" />
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Age -->
    <FormField v-slot="{ componentField }" name="age">
      <FormItem>
        <FormLabel>Tuổi</FormLabel>
        <FormControl>
          <Input v-bind="componentField" type="number" min="18" max="100" />
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Country Select -->
    <FormField v-slot="{ componentField }" name="country">
      <FormItem>
        <FormLabel>Quốc gia</FormLabel>
        <FormControl>
          <Select v-bind="componentField">
            <SelectTrigger>
              <SelectValue placeholder="Chọn quốc gia" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="vn">Việt Nam</SelectItem>
              <SelectItem value="us">Hoa Kỳ</SelectItem>
              <SelectItem value="jp">Nhật Bản</SelectItem>
            </SelectContent>
          </Select>
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Gender Radio -->
    <FormField v-slot="{ value, handleChange }" name="gender">
      <FormItem>
        <FormLabel>Giới tính</FormLabel>
        <FormControl>
          <RadioGroup :model-value="value" @update:model-value="handleChange" class="flex flex-col space-y-2">
            <div class="flex items-center space-x-2">
              <RadioGroupItem id="male" value="male" />
              <label for="male">Nam</label>
            </div>
            <div class="flex items-center space-x-2">
              <RadioGroupItem id="female" value="female" />
              <label for="female">Nữ</label>
            </div>
            <div class="flex items-center space-x-2">
              <RadioGroupItem id="other" value="other" />
              <label for="other">Khác</label>
            </div>
          </RadioGroup>
        </FormControl>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Interests Checkbox Array -->
    <FormField name="interests">
      <FormItem>
        <FormLabel class="text-base">Sở thích</FormLabel>
        <FormDescription>
          Chọn các sở thích của bạn
        </FormDescription>

        <FormField 
          v-for="interest in interests" 
          v-slot="{ value, handleChange }" 
          :key="interest.id" 
          type="checkbox" 
          :value="interest.id" 
          :unchecked-value="false" 
          name="interests"
        >
          <FormItem class="flex flex-row items-start space-x-3 space-y-0">
            <FormControl>
              <Checkbox
                :model-value="value.includes(interest.id)"
                @update:model-value="handleChange"
              />
            </FormControl>
            <FormLabel class="font-normal">
              {{ interest.label }}
            </FormLabel>
          </FormItem>
        </FormField>
        
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Description Textarea -->
    <FormField v-slot="{ componentField }" name="description">
      <FormItem>
        <FormLabel>Mô tả</FormLabel>
        <FormControl>
          <Textarea 
            v-bind="componentField"
            placeholder="Nhập mô tả về bản thân..."
            rows="4"
          />
        </FormControl>
        <FormDescription>
          Mô tả chi tiết về bản thân (tối đa 500 ký tự)
        </FormDescription>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Accept Terms Checkbox -->
    <FormField v-slot="{ value, handleChange }" name="acceptTerms">
      <FormItem class="flex flex-row items-start space-x-3 space-y-0">
        <FormControl>
          <Checkbox
            :model-value="value"
            @update:model-value="handleChange"
          />
        </FormControl>
        <FormLabel class="font-normal">
          Tôi đồng ý với điều khoản và điều kiện
        </FormLabel>
        <FormMessage />
      </FormItem>
    </FormField>

    <!-- Submit Button -->
    <div class="flex justify-start">
      <Button type="submit">
        Gửi form
      </Button>
    </div>
  </form>
</template>
```

## Các Pattern phổ biến

### 1. Reset form
```typescript
const { handleSubmit, resetForm } = useForm({
  validationSchema: formSchema,
});

// Reset về initial values
const handleReset = () => {
  resetForm();
};
```

### 2. Set giá trị động
```typescript
const { setFieldValue, setValues } = useForm({
  validationSchema: formSchema,
});

// Set một field
setFieldValue('username', 'john_doe');

// Set nhiều fields
setValues({
  username: 'john_doe',
  email: 'john@example.com',
});
```

### 3. Watch form values
```typescript
import { watch } from 'vue';

const { values } = useForm({
  validationSchema: formSchema,
});

watch(
  () => values.country,
  (newCountry) => {
    console.log('Country changed:', newCountry);
    // Logic xử lý khi country thay đổi
  }
);
```

### 4. Conditional fields
```vue
<FormField v-slot="{ componentField }" name="email">
  <FormItem>
    <FormLabel>Email</FormLabel>
    <FormControl>
      <Input v-bind="componentField" type="email" />
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>

<!-- Chỉ hiển thị khi email được điền -->
<FormField 
  v-if="values.email" 
  v-slot="{ value, handleChange }" 
  name="emailNotifications"
>
  <FormItem class="flex flex-row items-start space-x-3 space-y-0">
    <FormControl>
      <Checkbox
        :model-value="value"
        @update:model-value="handleChange"
      />
    </FormControl>
    <FormLabel>
      Nhận thông báo qua email
    </FormLabel>
  </FormItem>
</FormField>
```

## Best Practices

1. **Luôn wrap input trong FormControl**: Đảm bảo accessibility
2. **Sử dụng FormMessage**: Hiển thị lỗi validation tự động
3. **FormDescription cho các field phức tạp**: Giải thích rõ ràng
4. **Validation phía client và server**: Zod cho client, validation lại ở backend
5. **Initial values**: Luôn set initial values để tránh undefined
6. **TypeScript**: Sử dụng typed schema để có type safety

## Lưu ý quan trọng

- **Checkbox array**: Sử dụng `type="checkbox"`, `value="item_id"`, `unchecked-value="false"`
- **Radio group**: Sử dụng `value` và `handleChange` từ FormField slot
- **Select**: Bind `componentField` để có đầy đủ tính năng
- **Submit**: Sử dụng `handleSubmit` wrapper để có validation tự động
