export const ValidationRules = {
  specialization: {
    name: {
      minLength: 1,
      maxLength: 255,
    },
    code: {
      minLength: 1,
      maxLength: 50,
    },
    description: {
      maxLength: 1000,
    },
  },
  program: {
    name: {
      minLength: 1,
      maxLength: 255,
    },
    code: {
      maxLength: 50,
    },
    description: {
      maxLength: 1000,
    },
  },
  curriculumVersion: {
    versionCode: {
      minLength: 1,
      maxLength: 50,
    },
    notes: {
      maxLength: 1000,
    },
  },
  curriculumUnit: {
    note: {
      maxLength: 1000,
    },
    yearLevel: {
      min: 1,
      max: 5,
    },
    semesterNumber: {
      min: 1,
      max: 3,
    },
  },
  student: {
    firstName: {
      minLength: 1,
      maxLength: 100,
    },
    lastName: {
      minLength: 1,
      maxLength: 100,
    },
    middleName: {
      maxLength: 100,
    },
    email: {
      maxLength: 255,
    },
    phone: {
      maxLength: 20,
    },
    nationality: {
      maxLength: 100,
    },
    nationalId: {
      maxLength: 20,
    },
    parentGuardianName: {
      maxLength: 255,
    },
    parentGuardianPhone: {
      maxLength: 20,
    },
    parentGuardianEmail: {
      maxLength: 255,
    },
    emergencyContactName: {
      maxLength: 255,
    },
    emergencyContactPhone: {
      maxLength: 20,
    },
    highSchoolName: {
      maxLength: 255,
    },
  },
} as const;
