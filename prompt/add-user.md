## Implement the following features for the user management module:

### 1. **Add "Add User" Button**
   - Add a button labeled "Add User" above the user table.
   - When clicked, redirect to the path `/users/add`.

### 2. **Complete the UI for User Creation**
   - File to edit: `resources/js/pages/users/Add.vue`.
   - Get data from `RoleController`.
   - Build a form to input user details including:
     - **Email**
     - **Fullname**
   - Display a table of selectable **roles** fetched from the `Role` model.
     - Under each role, show a list of **permissions** associated with that role.
     - Group permissions by `parent_id` and display them in a user-friendly grouped format.

### 3. **Models Required**
   - `User`
   - `Role`
   - `Permission`
   - `CampusUserRole`
   - `RolePermission`
