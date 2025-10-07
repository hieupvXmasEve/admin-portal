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
    courseOffering: {
        section_code: {
            maxLength: 10,
        },
        max_capacity: {
            min: 1,
            max: 500,
        },
        waitlist_capacity: {
            min: 0,
            max: 100,
        },
        location: {
            maxLength: 255,
        },
        special_requirements: {
            maxLength: 1000,
        },
        notes: {
            maxLength: 1000,
        },
    },
    courseRegistration: {
        notes: {
            maxLength: 1000,
        },
    },
    scholarship: {
        code: {
            minLength: 1,
            maxLength: 50,
        },
        name: {
            minLength: 1,
            maxLength: 255,
        },
        description: {
            maxLength: 1000,
        },
        amount: {
            min: 0.01,
            max: 999999999.99,
        },
        percentage: {
            min: 0.01,
            max: 100,
        },
    },
} as const;

export const ValidationMessages = {
    courseRegistration: {
        student_id: {
            required: 'Please select a student',
        },
        course_offering_id: {
            required: 'Please select a course offering',
        },
        payment_status: {
            required: 'Please select payment status',
        },
        notes: {
            maxLength: 'Notes cannot exceed 1000 characters',
        },
    },
    courseOffering: {
        semester_id: {
            required: 'Semester is required',
        },
        unit_id: {
            required: 'Unit is required',
        },
        campus_id: {
            required: 'Campus is required',
        },
        course_code: {
            required: 'Course code is required',
            maxLength: 'Course code too long',
        },
        section_code: {
            maxLength: 'Section code too long',
        },
        course_title: {
            required: 'Course title is required',
            maxLength: 'Course title too long',
        },
        credit_hours: {
            required: 'Credit hours is required',
            range: 'Credit hours must be between 0 and 10',
        },
        max_enrollment: {
            required: 'Max enrollment is required',
            min: 'Max enrollment must be greater than 0',
        },
        waitlist_capacity: {
            min: 'Waitlist capacity must be 0 or greater',
        },
        delivery_mode: {
            required: 'Please select a delivery mode',
        },
        location: {
            maxLength: 'Location too long',
        },
        tuition_per_credit: {
            min: 'Tuition per credit must be 0 or greater',
        },
        additional_fees: {
            min: 'Additional fees must be 0 or greater',
        },
        registration_end_date: {
            afterOrEqual: 'Registration end date must be after or equal to start date',
        },
    },
    scholarship: {
        code: {
            required: 'Scholarship code is required',
            unique: 'This scholarship code has already been taken',
            pattern: 'Code must contain only uppercase letters, numbers, hyphens, and underscores',
            maxLength: 'Scholarship code cannot exceed 50 characters',
        },
        name: {
            required: 'Scholarship name is required',
            maxLength: 'Scholarship name cannot exceed 255 characters',
        },
        description: {
            maxLength: 'Description cannot exceed 1000 characters',
        },
        type: {
            required: 'Scholarship type is required',
            invalid: 'Scholarship type must be either percentage or fixed_amount',
        },
        amount: {
            required: 'Scholarship amount is required',
            positive: 'Scholarship amount must be greater than zero',
            max: 'Scholarship amount is too large',
            percentageMax: 'Percentage discount cannot exceed 100%',
        },
        valid_from: {
            required: 'Valid from date is required',
            invalid: 'Valid from must be a valid date',
        },
        valid_until: {
            required: 'Valid until date is required',
            invalid: 'Valid until must be a valid date',
            after: 'Valid until date must be after valid from date',
        },
    },
} as const;
