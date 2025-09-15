<template>
  <Card class="hover:shadow-lg transition-shadow cursor-pointer" @click="$emit('view', form)">
    <CardHeader>
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-2">
          <component :is="getIcon(form.type)" class="h-5 w-5 text-primary" />
          <Badge :variant="getTypeBadgeVariant(form.type)">
            {{ form.type }}
          </Badge>
        </div>
        <Badge v-if="isCompleted" variant="success">
          Completed
        </Badge>
        <Badge v-else-if="!form.can_submit" variant="secondary">
          Closed
        </Badge>
      </div>
      <CardTitle class="mt-3">{{ form.title }}</CardTitle>
      <CardDescription v-if="form.description">
        {{ form.description }}
      </CardDescription>
    </CardHeader>
    
    <CardContent>
      <div class="space-y-2 text-sm">
        <!-- Submission Info -->
        <div v-if="form.submission_count > 0" class="flex items-center gap-2 text-muted-foreground">
          <CheckCircle class="h-4 w-4" />
          <span>Submitted {{ form.submission_count }} time(s)</span>
        </div>

        <!-- Target Info -->
        <div v-if="form.targets && form.targets.length > 0" class="space-y-1">
          <div v-for="target in form.targets" :key="target.id" class="text-muted-foreground">
            <div v-if="target.is_active" class="flex items-center gap-2">
              <Clock class="h-4 w-4" />
              <span>
                Due: {{ formatDate(target.end_at) || 'No deadline' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Submission Limit -->
        <div v-if="form.targets && form.targets[0]?.submission_limit_per_user > 1" class="flex items-center gap-2 text-muted-foreground">
          <AlertCircle class="h-4 w-4" />
          <span>
            Max submissions: {{ form.targets[0].submission_limit_per_user }}
          </span>
        </div>
      </div>
    </CardContent>

    <CardFooter>
      <Button 
        v-if="form.can_submit" 
        class="w-full"
        @click.stop="$emit('view', form)"
      >
        <FileText class="mr-2 h-4 w-4" />
        Fill Out Form
      </Button>
      <Button 
        v-else-if="form.submission_count > 0"
        variant="outline"
        class="w-full"
        @click.stop="$emit('view', form)"
      >
        <Eye class="mr-2 h-4 w-4" />
        View Submission
      </Button>
      <div v-else class="w-full text-center text-sm text-muted-foreground">
        Form not available
      </div>
    </CardFooter>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { 
  FileText, 
  MessageSquare, 
  ClipboardList, 
  HelpCircle,
  CheckCircle,
  Clock,
  AlertCircle,
  Eye
} from 'lucide-vue-next'
import type { Form } from '@/types/forms'
import { format } from 'date-fns'

interface Props {
  form: Form
  isCompleted?: boolean
}

defineProps<Props>()
defineEmits<{
  view: [form: Form]
}>()

const getIcon = (type: string) => {
  switch (type) {
    case 'feedback':
      return MessageSquare
    case 'survey':
      return ClipboardList
    case 'query':
      return HelpCircle
    default:
      return FileText
  }
}

const getTypeBadgeVariant = (type: string) => {
  switch (type) {
    case 'feedback':
      return 'default'
    case 'survey':
      return 'secondary'
    case 'query':
      return 'outline'
    default:
      return 'default'
  }
}

const formatDate = (dateString?: string) => {
  if (!dateString) return null
  try {
    return format(new Date(dateString), 'MMM d, yyyy')
  } catch {
    return dateString
  }
}
</script>