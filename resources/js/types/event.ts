export interface Event {
  id: number
  campus_id: number
  title: string
  description: string | null
  start_time: string
  end_time: string
  location: string
  gold_reward_amount: number
  max_participants: number | null
  qr_code: string
  organizer_type: 'school'
  organizer_id: number
  status: 'draft' | 'published' | 'cancelled' | 'completed'
  published_at: string | null
  cancelled_at: string | null
  completed_at: string | null
  created_at: string
  updated_at: string

  // Relationships
  campus?: Campus
  creator?: {
    id: number
    name: string
    email: string
  }

  // Computed properties
  is_published: boolean
  is_cancelled: boolean
  is_completed: boolean
  can_register: boolean
  has_reached_capacity: boolean

  // Participant counts
  registered_count?: number
  checked_in_count?: number
  completed_count?: number
}

export interface EventParticipant {
  id: number
  event_id: number
  student_id: number
  status: 'registered' | 'checked_in' | 'completed' | 'cancelled'
  registered_at: string
  checkin_time: string | null
  checkin_device_info: Record<string, any> | null
  checkin_staff_id: number | null
  gold_awarded: boolean
  awarded_at: string | null
  created_at: string
  updated_at: string

  // Relationships
  event?: Event
  student?: Student
  checkin_staff?: User
}

export interface EventStatistics {
  registered_count: number
  checked_in_count: number
  completed_count: number
  cancelled_count: number
  total_gold_distributed: number
  participation_rate: number | null
}

export interface EventFilters {
  search?: string
  status?: 'draft' | 'published' | 'cancelled' | 'completed'
  date_from?: string
  date_to?: string
}

export interface PaginatedEvents {
  data: Event[]
  links: PaginationLink[]
  from: number
  to: number
  total: number
  per_page: number
  current_page: number
  last_page: number
  prev_page_url: string | null
  next_page_url: string | null
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

// Import commonly used types
import type { Campus, Student, User } from './models'
