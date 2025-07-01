### **Prompt cho AI Agent: Laravel 12 + Vue3 + Inertia.js CRUD App**

> You are a full-stack AI developer. Please perform the following tasks step-by-step, using Laravel 12, Vue 3, Inertia.js, vee-validate, vue-sonner, and Ziggy. Ensure proper structure and conventions are followed.

### **Requirements**

### **1. Backend Setup (Laravel 12)**
- Generate a `Campus` model with:
    - Migration
    - Factory
    - Seeder
- Define the following fields in `campuses` table:
```sql
create table campuses
(
    id         bigint unsigned auto_increment primary key,
    name       varchar(255) not null,
    code       varchar(255) not null,
    address    varchar(255) not null,
    created_at timestamp    null,
    updated_at timestamp    null,
    constraint campuses_code_unique
        unique (code)
)
```
- Generate a `Building` model with:
    - Migration
    - Factory
    - Seeder
- Define the following fields in `buildings` table:
```sql
create table buildings
(
    id         bigint unsigned auto_increment primary key,
    campus_id  bigint unsigned not null,
    name       varchar(100) not null,
    code       varchar(20) not null,
    description text,
    address    text,
    created_at timestamp    null,
    updated_at timestamp    null,
    deleted_at timestamp    null,
    constraint buildings_code_unique
        unique (code)
);
```
- Populate the database using the factory and seeder.

### **2. Vue 3 + Inertia.js Frontend Setup**

- Ensure the frontend stack uses Vue 3 and Inertia.js.
- Create the following pages in `resources/js/Pages/Campuses/`:
    - `Index.vue`
    - `Create.vue`
    - `Edit.vue` (edit campus)
    - `Form.vue` (reusable form component)
- Create the following pages in `resources/js/Pages/Buildings/`:
    - `Index.vue`
    - `Create.vue`
    - `Edit.vue` (edit building)
    - `Form.vue` (reusable form component)

### **3. Frontend Validation (vee-validate)**

- Install `vee-validate` and `zod`.
- Use `vee-validate` with `zod` schema to validate the `Form.vue` fields on the frontend.
- Show error messages below each field on validation fail.

### **4. Form Submission (useForm)**

- Use Inertia’s `useForm` to bind and submit the form data to backend routes:
    - `campuses.store`
    - `campuses.update`
    - `buildings.store`
    - `buildings.update`
- Handle error responses and populate them in the form.

### **5. Notifications (vue-sonner)**

- Install and configure `vue-sonner`.
- Display toast on:
    - Success (e.g., "Campus created successfully")
    - Error (e.g., "Something went wrong")
- Add `<Toaster />` component in the main layout.

### **6. Route Sync (Ziggy)**

- Install Ziggy (backend and frontend).
- Use Ziggy's `/resources/js/utils/routes.ts` helper in Vue components to generate URLs.

---

### **Bonus Behaviors**

- Use named Laravel routes (`Route::name()`) for all endpoints.
- Redirect back to index page on successful create/update/delete.
- Confirm before deleting an item.

---

### **Final Check**

- All CRUD pages work properly (index, create, edit, delete) for campuses and buildings .
- Validation occurs on both frontend and backend.
- Toasts display as expected.
- All route URLs in Vue are generated using Ziggy `route()`.