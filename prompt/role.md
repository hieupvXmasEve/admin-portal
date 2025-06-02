Update and enhance the role management module with the following features:

1. **Ensure Data Refresh on Navigation**
   - Fix the issue where navigating between pages does not load the latest data. Make sure data is fetched fresh on each page load.

2. **Add Role Creation Page**
   - Create a new "Add Role" page similar in layout and structure to the `Add User` page.
   - Include a form for entering role details and selecting permissions.

3. **Update Role Editing Page**
   - Refactor the existing "Edit Role" page to match the UI and structure of the "Add Role" page.
   - Pre-fill the form with the role's current data for editing.

4. **Permission Selection UI**
   - Display permissions using checkboxes grouped by their `parent_id`.
   - **Do not display a checkbox for parent permissions**.
   - Instead, show a single checkbox next to the group title (based on the parent permission).
   - When the group checkbox (parent) is selected, automatically select all child permissions.
   - If any child permission is deselected, automatically uncheck the parent/group checkbox.

5. **Models Required**
   - `Role`
   - `Permission`
