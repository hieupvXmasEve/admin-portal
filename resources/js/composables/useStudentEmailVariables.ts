import { computed, type Ref } from 'vue';
import type { BulkEmailStudent, EmailVariable } from '@/types/email';
import { 
  STUDENT_EMAIL_VARIABLES, 
  extractStudentVariables, 
  getStudentSampleData 
} from '@/types/email';

export function useStudentEmailVariables(students: Ref<BulkEmailStudent[]>) {
  
  // Get variables available for the current students
  const availableVariables = computed<EmailVariable[]>(() => {
    return STUDENT_EMAIL_VARIABLES;
  });

  // Build per-recipient template variables
  const buildPerRecipientVariables = () => {
    const vars: Record<string, Record<string, string>> = {};
    students.value.forEach((student) => {
      vars[student.email] = extractStudentVariables(student);
    });
    return vars;
  };

  // Build global template variables using first student as fallback
  const buildGlobalVariables = (requiredVariables: string[]) => {
    const globalVars: Record<string, string> = {};
    
    if (students.value.length > 0) {
      const firstStudentVars = extractStudentVariables(students.value[0]);
      
      requiredVariables.forEach(variable => {
        globalVars[variable] = firstStudentVars[variable] || '';
      });
    } else {
      // Use empty fallbacks if no students
      requiredVariables.forEach(variable => {
        globalVars[variable] = '';
      });
    }

    return globalVars;
  };

  // Get sample data for preview
  const getSampleData = computed(() => {
    return getStudentSampleData(students.value);
  });

  // Check if a variable is available from student data
  const isStudentVariable = (variableName: string): boolean => {
    return STUDENT_EMAIL_VARIABLES.some(v => v.name === variableName);
  };

  // Get description for a student variable
  const getVariableDescription = (variableName: string): string => {
    const variable = STUDENT_EMAIL_VARIABLES.find(v => v.name === variableName);
    return variable?.description || variableName;
  };

  return {
    availableVariables,
    buildPerRecipientVariables,
    buildGlobalVariables,
    getSampleData,
    isStudentVariable,
    getVariableDescription,
  };
}
