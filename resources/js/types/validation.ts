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
} as const;
