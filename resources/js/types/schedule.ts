export interface ScheduleSession {
  id: number
  title: string
  unitCode: string
  section: string
  lecturer: string
  room: string
  startTime: string
  endTime: string
  date: string
  campusName: string
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled'
  sessionType: string
  deliveryMode: string
}

export interface DetailedScheduleSession {
  id: number
  title: string
  description: string
  unitCode: string
  unitName: string
  section: string
  lecturer: {
    id: number | null
    name: string
    email: string | null
  }
  room: {
    id: number | null
    name: string
    code: string | null
    building: string | null
    fullCode: string
  }
  campus: {
    id: number | null
    name: string
    code: string | null
  }
  schedule: {
    date: string
    startTime: string
    endTime: string
    duration: number
    formattedDate: string
    formattedTime: string
  }
  details: {
    sessionType: string
    deliveryMode: string
    status: string
    statusBadgeColor: string
    attendanceRequired: boolean
    attendanceTrackingEnabled: boolean
    isAssessment: boolean
    assessmentWeight: number | null
    sequenceNumber: number
  }
  attendance: {
    expectedAttendees: number | null
    actualAttendees: number | null
    attendancePercentage: number | null
    stats: {
      total: number
      present: number
      late: number
      absent: number
      excused: number
      attendance_percentage: number
    }
  }
  metadata: {
    learningObjectives: string[] | null
    requiredMaterials: string[] | null
    topicsCovered: string[] | null
    instructorNotes: string | null
    adminNotes: string | null
    studentInstructions: string | null
  }
  timestamps: {
    scheduledAt: string | null
    startedAt: string | null
    endedAt: string | null
    cancelledAt: string | null
    createdAt: string
    updatedAt: string
  }
}

export interface ScheduleFilters {
  semester_id?: number
  lecturer_id?: number
  room_id?: number
  date_range?: {
    start: string
    end: string
  }
}

export interface FilterOptions {
  semesters: Array<{
    id: number
    name: string
    academic_year: string
  }>
  lecturers: Array<{
    id: number
    name: string
    email: string
  }>
  rooms: Array<{
    id: number
    name: string
    code: string
    building: string
    campus_id: number
    campus: {
      id: number
      name: string
    }
  }>
}

export interface SessionUpdateData {
  session_date: string
  start_time: string
  end_time: string
  room_id: number
}

export interface WeeklyGridData {
  [date: string]: ScheduleSession[]
}

export interface TimeSlot {
  hour: number
  displayTime: string
}

export interface ConflictError {
  room?: string
  lecturer?: string
  time?: string
}