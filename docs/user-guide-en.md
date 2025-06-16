# 👥 User Guide

Complete user guide for the Swinburne Project Management System.

## 📋 Overview

The Swinburne Project Management System is a comprehensive educational management platform designed to streamline administrative processes in university environments. The system provides role-based access control with multi-campus support.

## 🚀 Getting Started

### System Access
- **URL**: Access through your organization's provided URL
- **Login**: Use your assigned email and password
- **Support**: Contact your system administrator for access issues

### Default Admin Account (Development)
- **Email**: `hieupv2412@gmail.com`
- **Password**: `123456`

## 🏢 System Structure

### Campus Organization
The system supports multiple campus locations:
- **HN** - Swinburne Hà Nội
- **HCM** - Swinburne Hồ Chí Minh
- **DN** - Swinburne Đà Năng
- **CT** - Swinburne Cần Thơ

### Role-Based Access
Users can have different roles across different campuses:
- **Super Admin** - Full system access
- **Giám Đốc Đào Tạo** - Training Director
- **Trường Phòng Student HQ** - Student HQ Department Head
- **Trường Phòng Hành Chính** - Administrative Department Head
- **Trường Ban Tuyển Sinh** - Admissions Department Head
- **Trường Phòng Tuyển Sinh** - Admissions Office Head
- **Cán Bộ Đào Tạo** - Training Officer
- **Cán Bộ Student HQ** - Student HQ Officer
- **Cán Bộ Hành Chính** - Administrative Officer
- **Cán Bộ Tuyển Sinh** - Admissions Officer
- **Giảng Viên** - Lecturer

## 👥 User Management

### Viewing Users
1. Navigate to **Users** from the main menu
2. View user list with filtering and search options
3. Use pagination to browse through large user lists
4. Filter by campus, role, or search by name/email

### Adding Users
1. Click **Add User** button
2. Fill in required information:
   - Name (required)
   - Email (required)
   - Password
   - Phone number
   - Address
3. Assign campus and role combinations
4. Save to create the user

### Editing Users
1. Click **Edit** button next to user in the list
2. Modify user information as needed
3. Update campus-role assignments
4. Save changes

### User Import/Export

#### Importing Users
1. Navigate to **Users** → **Import Users**
2. Choose import format:
   - **Simple Format**: Single sheet with all information
   - **Detailed Format**: Multiple sheets for users and roles
   - **Relationship Format**: One row per campus-role relationship
3. Download template for your chosen format
4. Fill in the template with user data
5. Upload the completed file
6. Configure import options:
   - Duplicate handling (Skip/Update/Error)
   - Create missing campuses option
7. Review preview and process import

#### Exporting Users
1. Go to **Users** list page
2. Click **Export Excel** button
3. File downloads automatically with timestamp
4. Export includes all user information and campus-role assignments

## 📊 Data Management

### Import Templates
The system provides three import formats:

**Simple Format Headers:**
- Name* (required)
- Email* (required)
- Password
- Campus Codes (comma-separated)
- Role Codes (comma-separated)
- Phone
- Address

**Detailed Format:**
- Sheet 1: User basic information
- Sheet 2: Campus-role relationships

**Relationship Format:**
- One row per user-campus-role combination

### File Requirements
- **Supported formats**: .xlsx, .xls
- **Maximum file size**: 2MB (configurable)
- **Required fields**: Marked with asterisk (*)
- **Campus codes**: Must exist in system
- **Role codes**: Must use snake_case format

## 🔧 System Features

### Search and Filtering
- **Global search**: Search across all user fields
- **Campus filter**: Filter users by campus
- **Role filter**: Filter users by role
- **Status filter**: Filter by active/inactive status

### Pagination
- Navigate through large datasets efficiently
- Configurable items per page
- Jump to specific pages

### Data Validation
- Real-time form validation
- Server-side validation for data integrity
- Clear error messages for invalid data

### Permissions System
- Role-based access control
- Campus-specific permissions
- Feature-level permission checks

## 🛠️ Troubleshooting

### Common Issues

**Login Problems:**
- Verify email and password
- Check if account is active
- Contact administrator for password reset

**Import Errors:**
- Check file format and size limits
- Verify required headers are present
- Ensure campus and role codes exist
- Check for duplicate emails

**Permission Denied:**
- Verify your role has required permissions
- Check if you're assigned to the correct campus
- Contact administrator for permission updates

**File Upload Issues:**
- Check file size (max 2MB)
- Verify file format (.xlsx or .xls)
- Ensure stable internet connection

### Getting Help
1. Check error messages for specific guidance
2. Review this user guide for procedures
3. Contact your system administrator
4. Report bugs to the development team

## 📱 Best Practices

### Data Entry
- Use consistent formatting for names and addresses
- Verify email addresses before entry
- Use strong passwords for new users
- Double-check campus and role assignments

### Import/Export
- Always download and use current templates
- Test with small batches before large imports
- Keep backup copies of import files
- Review import results carefully

### Security
- Log out when finished using the system
- Don't share login credentials
- Report suspicious activity
- Keep personal information updated

## 🔗 Navigation

### Main Menu Items
- **Dashboard**: System overview and quick actions
- **Users**: User management and import/export
- **Campuses**: Campus information and settings
- **Roles**: Role management and permissions
- **Reports**: System reports and analytics
- **Settings**: System configuration options

### Quick Actions
- **Add User**: Create new user account
- **Import Users**: Bulk user import
- **Export Data**: Download user data
- **Search**: Find specific users or data

## 📞 Support

### Contact Information
- **System Administrator**: Contact your organization's IT department
- **Technical Support**: Report bugs or technical issues
- **Training**: Request additional training on system features

### Resources
- **User Guide**: This document
- **Video Tutorials**: Available in the help section
- **FAQ**: Common questions and answers
- **Release Notes**: Information about system updates

---

*Last updated: {{ date('Y-m-d') }}*
