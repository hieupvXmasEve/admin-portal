# Admin Interface: Course Registration Management

This interface handles two types of student course registration:

1. **Program-based Registration** – for newly enrolled students following a predefined curriculum.
2. **Retake Registration** – for returning students needing to retake failed courses.

---

## 🔹 Main Admin Interface Layout

Use a clear structure with two tabs or sections:

- **[Program-based Registration]**
- **[Retake Registration]**

---

## 🔹 [Program-based Registration] UI

### 📌 Purpose:
Bulk register courses for new students based on a fixed program structure.

### **Steps:**

1. **Select Semester**  
   e.g., SUM2025, FALL2025

2. **Select Program or Cohort**  
   e.g., IT-K47, Business-K46

3. **Display Student List**  
   Auto-fetch students belonging to the selected program and semester.

4. **Show Fixed Course List**  
   Courses predefined for the selected semester in the curriculum.

5. **Bulk Registration Button**  
   - Automatically creates course enrollment records for all listed students.
   - Include a warning for students who are already registered.

> Optional: Add support for elective courses in later semesters.

---
