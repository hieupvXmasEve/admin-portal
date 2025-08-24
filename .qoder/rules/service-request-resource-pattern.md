---
trigger: glob
glob: *.php
---
**Objective:**
Strictly follow the `Service-Request-Resource Pattern` when implementing or modifying any module in a Laravel application. Ensure the codebase is well-structured, maintainable, and scalable.

---

## Required Actions for Each Module (e.g., `Campus`)

### 1. Prepare the Folder Structure

Ensure the following directories exist:

- `app/Http/Controllers/Web`
- `app/Http/Controllers/Api`
- `app/Services`
- `app/Http/Requests/<ModuleName>`
- `app/Http/Resources/<ModuleName>`

### 2. Implement the Module Logic

- **Service**
  Create a `Service` class inside `app/Services`.
  This class is responsible for:
  - Creating new entities
  - Updating entities
  - Deleting entities
  - Logging, event dispatching, and other business logic

- **Form Request**
  Create or move validation request classes into `app/Http/Requests/<ModuleName>`.
  Ensure:
  - Namespaces match the folder structure
  - Controllers reference the correct request classes

- **API Resource**
  Create a resource class inside `app/Http/Resources/<ModuleName>` to format API output.
  The resource should:
  - Return a clean JSON structure
  - Include relevant fields like ID, name, timestamps

- **Web Controller**
  Create or move the controller into `app/Http/Controllers/Web`.
  The controller should:
  - Use the prepared FormRequests
  - Call the corresponding Service
  - Return views via Inertia

- **API Controller**
  Create the controller in `app/Http/Controllers/Api`.
  This controller should:
  - Accept requests through FormRequests
  - Use the Service for business logic
  - Return responses via Resource
  - Follow RESTful principles

### 3. Configure Routes

- **Web Routes:**
  - Use the Web controller
  - Ensure proper namespace: `use App\Http\Controllers\Web\<ModuleName>Controller`


- **API Routes:**
  - Add them to `routes/api.php`
  - Wrap in `Route->group(...)`
  - Use `Route::apiResource()` with correct namespace: `App\Http\Controllers\Api\`

<!-- ### 4. Test and Verify

- Run `php artisan test`
- Ensure all tests pass, especially avoiding "Class not found" errors
- If errors occur, check namespace and `use` statements -->

---

## Key Rules

- **All business logic must reside in Service classes**
- **Controllers should only coordinate: accept request → call service → return result**
- **Never place model logic directly inside controllers**
- **Web and API controllers must be clearly separated**
- **Each module must have its own Service + FormRequest + Resource + Controllers**

---

Whenever you are asked to create or modify a module, you must follow this exact sequence. If any step is missing, automatically complete it or ask for clarification if needed.
