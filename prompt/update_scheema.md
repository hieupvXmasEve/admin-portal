**Prompt for AI Agent: Generate Migration, Model, and Seeder for a New Curriculum Version**

### Objective

Update the academic system to support a new **curriculum\_version**, including:

* Migrations for `program_tracks`, `track_units`, and `curriculum_units` if not present
* Corresponding models in Laravel (or other frameworks if required)
* Sample Seeder to insert new program version with tracks and associated units

---

### Specific Requirements:

#### 1. Migration

* If `program_tracks` and `track_units` tables do not exist:

  * Create `program_tracks` table:

    * Fields: `id`, `curriculum_version_id`, `name`, `code`, `type` (ENUM: major, co\_major, minor, second\_major)
    * `FOREIGN KEY (curriculum_version_id)` references `curriculum_versions(id)`
  * Create `track_units` table:

    * Fields: `id`, `track_id`, `unit_id`, `type` (ENUM: core, major, elective)
    * `FOREIGN KEY (track_id)` → `program_tracks(id)`
    * `FOREIGN KEY (unit_id)` → `units(id)`
* Check if `curriculum_units` table exists. If not, create:

  * Fields: `curriculum_version_id`, `unit_id`, `type`

#### 2. Model

* Generate Laravel models (or corresponding models in other frameworks):

  * `ProgramTrack` model:

    * belongsTo `CurriculumVersion`
    * hasMany `TrackUnit`
  * `TrackUnit` model:

    * belongsTo `ProgramTrack`
    * belongsTo `Unit`
  * `CurriculumUnit` model:

    * belongsTo `CurriculumVersion`
    * belongsTo `Unit`

#### 3. Sample Seeder (Laravel style)

* Seeder to create a new `curriculum_version` (e.g., v2.0)
* Create 2–3 `program_tracks` under this version
* Attach 4–5 `units` to each track using `track_units`
* Attach 2–3 common `units` to the overall curriculum using `curriculum_units`

---

### Required Input from User:

* `program_id` of the program being updated
* `version_code` and `semester_id` for the new curriculum\_version
* List of tracks: name + type (major, minor...)
* List of units per track and their group\_type
* List of units shared across the entire program (if any)

### Expected Output:

* Laravel migration files (.php) for missing tables
* Laravel model files with correct relationships
* Seeder file or `php artisan db:seed` instructions to populate example data
