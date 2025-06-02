Update the user management module with the following changes:

1. **Remove Edit User Popup**
   - Remove the existing popup/modal for editing a user.

2. **Redirect to Edit Page**
   - When the "Edit" button is clicked for a user, redirect to `/users/edit`.

3. **Edit Page UI**
   - Create the edit page interface similar to the one used in `Add.vue`.
   - Populate the form with the selected user's data.
   - The following fields must be:
     - **Email**: pre-filled and **read-only**
     - **Fullname**: pre-filled and **read-only**

4. **Data Handling**
   - Fetch the user's data from the backend when entering the edit page.
   - Use the same layout and structure as the add user page.

5. **File to update** (if needed): `resources/js/pages/users/Edit.vue`
