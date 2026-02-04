# Module Migration Rules (Monolith -> Modular)

## 1. Core Principles

- **Target**: Migrate independent modules one by one.
- **Shared Models**: Continue using `App\Models`. **DO NOT** move models initially.
- **Independence**: Modules must NOT depend on other modules (except `Identity` and `Shared`).
- **Logic**: No business logic in Controllers; move to Actions.

## 2. Migration Flow

### Step 1: Create Module Structure

- create `app/Modules/{Module}/` with Actions, Http, policies, etc.
- **DO NOT** move models.

### Step 2: Extract Logic

- **Legacy Controller**: Contains 50+ lines of logic? -> Extract to Action.
- **Action**: Single use-case, no view return.

### Step 3: Use Shared Models

- Import `App\Models\User`.
- **Prohibited**: Moving models to `Modules/{Module}/Models` (initially).

### Step 4: Policies

- Move Policies to `Modules/{Module}/Policies`.
- Register in Gate/Policy map.

### Step 5: Routes

- Move routes to `Modules/{Module}/routes/web.php` or `api.php`.
- Ensure routes point to the new Module Controllers.

### Step 6: Controller as Adapter

- Validate Request.
- Call Action.
- Return Response (Inertia/JSON).

## 3. Strict Prohibitions

- ❌ **Cross-Module Imports**: e.g., `Academic` importing `Finance`.
- ❌ **Model Logic**: Do not add business logic to Eloquent models.
- ❌ **Moving Models Early**: Wait until the module is stable (6-12 months).

## 4. Definition of Done

A module is migrated when:

- [ ] Legacy Controller has no logic.
- [ ] Actions reside in the Module.
- [ ] Policies reside in the Module.
- [ ] No cross-module imports exist.
