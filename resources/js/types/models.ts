export interface Specialization {
  id: number;
  program_id: number;
  name: string;
  code: string;
  description?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  program?: Program;
  curriculum_versions_count?: number;
  curriculum_versions?: CurriculumVersion[];
}

export interface Program {
  id: number;
  name: string;
  code?: string;
  description?: string;
  created_at: string;
  updated_at: string;
  specializations?: Specialization[];
  curriculum_versions?: CurriculumVersion[];
}

export interface CurriculumVersion {
  id: number;
  program_id: number;
  specialization_id?: number;
  version_code: string;
  semester_id?: number;
  notes?: string;
  created_at: string;
  updated_at: string;
  specialization?: Specialization;
  program?: Program;
  effective_from_semester?: Semester;
  curriculum_units_count?: number;
  curriculum_units?: CurriculumUnit[];
}

export interface Semester {
  id: number;
  name: string;
  code: string;
  created_at: string;
  updated_at: string;
}

export interface Unit {
  id: number;
  code: string;
  name: string;
  credit_points: number;
  created_at: string;
  updated_at: string;
}

export interface UnitType {
  id: number;
  name: string;
  description?: string;
  created_at: string;
  updated_at: string;
}

export interface CurriculumUnit {
  id: number;
  curriculum_version_id: number;
  unit_id: number;
  unit_type_id?: number;
  year_level?: number;
  semester_number?: number;
  note?: string;
  created_at: string;
  updated_at: string;
  unit?: Unit;
  unit_type?: UnitType;
  curriculum_version?: CurriculumVersion;
}

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Campus {
  id: number;
  name: string;
  code: string;
  address?: string;
  created_at: string;
  updated_at: string;
}

export interface Student {
  id: number;
  student_id: string;
  full_name: string;
  email: string;
  phone?: string;
  date_of_birth?: string;
  gender?: 'male' | 'female' | 'other';
  nationality?: string;
  national_id?: string;
  address?: string;
  avatar_url?: string;
  campus_id: number;
  program_id: number;
  specialization_id?: number;
  curriculum_version_id: number;
  admission_date: string;
  expected_graduation_date?: string;
  enrollment_status: 'admitted' | 'enrolled' | 'active' | 'on_leave' | 'suspended' | 'graduated' | 'dropped_out';
  parent_guardian_name?: string;
  parent_guardian_phone?: string;
  parent_guardian_email?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  high_school_name?: string;
  high_school_graduation_year?: number;
  entrance_exam_score?: number;
  admission_notes?: string;
  status: 'active' | 'inactive' | 'suspended';
  campus?: Campus;
  program?: Program;
  specialization?: Specialization;
  curriculum_version?: CurriculumVersion;
  created_at: string;
  updated_at: string;
}

export interface CourseRegistration {
  id: number;
  student_id: number;
  course_offering_id: number;
  semester_id: number;
  registration_status: 'registered' | 'confirmed' | 'dropped' | 'withdrawn' | 'completed';
  registration_date: string;
  registration_method: 'online' | 'advisor' | 'admin_override';
  credit_hours: number;
  final_grade?: string;
  grade_points?: number;
  attempt_number: number;
  is_retake: boolean;
  tuition_amount: number;
  fees_amount: number;
  payment_status: 'pending' | 'paid' | 'overdue' | 'waived';
  course_offering?: CourseOffering;
  semester?: Semester;
  created_at: string;
  updated_at: string;
}

export interface CourseOffering {
  id: number;
  semester_id: number;
  unit_id: number;
  campus_id: number;
  course_code: string;
  section_code: string;
  course_title: string;
  credit_hours: number;
  max_enrollment: number;
  current_enrollment: number;
  waitlist_capacity: number;
  current_waitlist: number;
  delivery_mode: 'in_person' | 'online' | 'hybrid';
  schedule?: any;
  location?: string;
  instructor_id?: number;
  prerequisites?: string[];
  status: 'active' | 'cancelled' | 'full' | 'closed';
  tuition_per_credit: number;
  additional_fees: number;
  unit?: Unit;
  instructor?: User;
  semester?: Semester;
  campus?: Campus;
  created_at: string;
  updated_at: string;
}

export interface AcademicHold {
  id: number;
  student_id: number;
  hold_type: 'financial' | 'academic' | 'disciplinary' | 'administrative' | 'health' | 'library';
  hold_category: 'registration' | 'graduation' | 'transcript' | 'all';
  title: string;
  description?: string;
  amount?: number;
  priority: 'high' | 'medium' | 'low';
  status: 'active' | 'resolved' | 'waived' | 'expired';
  placed_date: string;
  due_date?: string;
  resolved_date?: string;
  placed_by_user_id?: number;
  resolved_by_user_id?: number;
  resolution_notes?: string;
  student?: Student;
  placed_by_user?: User;
  resolved_by_user?: User;
  created_at: string;
  updated_at: string;
}

export interface GraduationRequirement {
  id: number;
  program_id: number;
  specialization_id?: number;
  total_credits_required: number;
  core_credits_required: number;
  major_credits_required: number;
  elective_credits_required: number;
  minimum_gpa: number;
  minimum_major_gpa: number;
  maximum_study_years: number;
  required_internship: boolean;
  required_thesis: boolean;
  required_english_certification: boolean;
  special_requirements?: any;
  effective_from: string;
  effective_to?: string;
  is_active: boolean;
  program?: Program;
  specialization?: Specialization;
  created_at: string;
  updated_at: string;
}
