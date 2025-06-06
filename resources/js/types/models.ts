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
