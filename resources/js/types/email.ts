// Student data type for bulk email functionality
export interface BulkEmailStudent {
  id: number;
  student_id: string;
  fullname: string;
  email: string;
  curriculum_version_code?: string;
}

// Email variable definition for editor components
export interface EmailVariable {
  name: string;
  description: string;
  value?: string; // Optional default value
}

// Common variables that can be derived from BulkEmailStudent
export const STUDENT_EMAIL_VARIABLES: EmailVariable[] = [
  { name: 'student_name', description: 'Student Name' }, // derived from fullname
  { name: 'student_id', description: 'Student ID' },
  { name: 'student_email', description: 'Student Email' }, // derived from email
  { name: 'curriculum_version_code', description: 'Curriculum Version Code' },
];

// Additional common variables for general email templates
export const COMMON_EMAIL_VARIABLES: EmailVariable[] = [
  { name: 'course_name', description: 'Course Name' },
  { name: 'due_date', description: 'Due Date' },
  { name: 'semester', description: 'Semester' },
  { name: 'academic_year', description: 'Academic Year' },
  { name: 'instructor_name', description: 'Instructor Name' },
];

// Combined default variables
export const DEFAULT_EMAIL_VARIABLES: EmailVariable[] = [
  ...STUDENT_EMAIL_VARIABLES,
  ...COMMON_EMAIL_VARIABLES,
];

// Function to extract variables from a BulkEmailStudent object
export function extractStudentVariables(student: BulkEmailStudent): Record<string, string> {
  return {
    student_name: student.fullname || '',
    student_id: student.student_id || '',
    student_email: student.email || '',
    curriculum_version_code: student.curriculum_version_code || '',
  };
}

// Function to get sample data for preview
export function getStudentSampleData(students: BulkEmailStudent[]): Record<string, string> {
  if (students.length === 0) {
    // Return default sample data
    return {
      student_name: 'John Smith',
      student_id: 'STU123456',
      student_email: 'john.smith@example.com',
      curriculum_version_code: 'v2024.1',
      // Add common email variables as defaults
      course_name: 'Introduction to Programming',
      due_date: 'March 15, 2024',
      semester: 'Spring 2024',
      academic_year: '2024',
      instructor_name: 'Dr. Jane Doe',
    };
  }

  // Use data from first student as sample
  const firstStudent = students[0];
  return {
    ...extractStudentVariables(firstStudent),
    // Add default values for non-student specific variables
    course_name: 'Sample Course Name',
    due_date: 'March 15, 2024',
    semester: 'Spring 2024',
    academic_year: '2024',
    instructor_name: 'Dr. Sample Instructor',
  };
}
