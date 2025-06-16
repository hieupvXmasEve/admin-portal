# Employee Guide: Swinx User Management System

## Table of Contents
1. [Project Setup](#project-setup)
2. [Database Setup and Seeding](#database-setup-and-seeding)
3. [Understanding the System](#understanding-the-system)
4. [User Import Functionality](#user-import-functionality)
5. [User Export Functionality](#user-export-functionality)
6. [File Management](#file-management)
7. [Troubleshooting](#troubleshooting)

---

## Project Setup

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL/MariaDB
- Laravel Herd (recommended) or similar local development environment

### 1. Clone and Install Dependencies

```bash
# Clone the repository
git clone <repository-url>
cd swinx

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Configure Environment

Edit `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swinx
DB_USERNAME=your_username
DB_PASSWORD=your_password

# File upload limits (adjust based on your PHP configuration)
IMPORT_MAX_FILE_SIZE=2MB
```

### 3. Build Assets

```bash
# Build frontend assets
npm run build

# For development with hot reload
npm run dev
```

---

## Database Setup and Seeding

### 1. Create Database

```bash
# Create database (if using MySQL command line)
mysql -u root -p
CREATE DATABASE swinx;
exit
```

### 2. Run Migrations and Seeders

```bash
# Run fresh migration with seeders (this will recreate all tables)
php artisan migrate:fresh --seed
```

This command will create:
- **4 Campuses**: Hà Nội (HN), Hồ Chí Minh (HCM), Đà Năng (DN), Cần Thơ (CT)
- **11 Roles** with auto-generated codes:
  - Super Admin (`super_admin`)
  - Giám Đốc Đào Tạo (`giam_doc_dao_tao`)
  - Trường Phòng Student HQ (`truong_phong_student_hq`)
  - Trường Phòng Hành Chính (`truong_phong_hanh_chinh`)
  - Trường Ban Tuyển Sinh (`truong_ban_tuyen_sinh`)
  - Trường Phòng Tuyển Sinh (`truong_phong_tuyen_sinh`)
  - Cán Bộ Đào Tạo (`can_bo_dao_tao`)
  - Cán Bộ Student HQ (`can_bo_student_hq`)
  - Cán Bộ Hành Chính (`can_bo_hanh_chinh`)
  - Cán Bộ Tuyển Sinh (`can_bo_tuyen_sinh`)
  - Giảng Viên (`giang_vien`)
- **70 Permissions** with proper hierarchy
- **1 Admin User**: `hieupv2412@gmail.com` / `123456`
- **200 Random Users** for testing

### 3. Start the Application

```bash
# Start the Laravel development server
php artisan serve

# The application will be available at http://localhost:8000
```

---

## Understanding the System

### User Structure
Each user can have multiple roles across different campuses through the `campus_user_roles` table:
- **User**: Basic information (name, email, password, phone, address)
- **Campus**: Location-based organization
- **Role**: Permission-based access control
- **Relationship**: User + Campus + Role combination

### Role Codes
- Roles now use **codes** instead of names for import/export
- Codes are automatically generated in `snake_case` format
- Example: "Super Admin" → `super_admin`
- Codes can be manually edited if needed

---

## User Import Functionality

### 1. Access Import Page

1. Login to the system
2. Navigate to **Users** → **Import Users** or visit `/users/import`
3. Ensure you have `import_user` permission

### 2. Import Formats

The system supports **3 import formats**:

#### A. Simple Format (Single Sheet)
**Headers**: `Name*`, `Email*`, `Password`, `Campus Codes`, `Role Codes`, `Phone`, `Address`

**Example**:
```
Name*           | Email*              | Password    | Campus Codes | Role Codes              | Phone       | Address
John Doe        | john@example.com    | password123 | HN,HCM      | super_admin,can_bo_dao_tao | +1234567890 | 123 Main St
Jane Smith      | jane@example.com    | password123 | DN          | giang_vien              | +0987654321 | 456 Oak Ave
```

#### B. Detailed Format (Multiple Sheets)
**Sheet 1 - Users**: `Name*`, `Email*`, `Password`, `Phone`, `Address`, `Email Verified`
**Sheet 2 - User Campus Roles**: `User Email*`, `Campus Code*`, `Role Code*`, `Assigned Date`

#### C. Relationship Format (One Row Per Relationship)
**Headers**: `User Name*`, `User Email*`, `Campus Code*`, `Campus Name`, `Role Code*`, `Password`, `Phone`

### 3. Download Templates

```bash
# Templates are generated dynamically and can be downloaded from:
# - /users/templates/simple
# - /users/templates/detailed  
# - /users/templates/relationship
```

Or use the "Download Template" buttons in the import interface.

### 4. Create Sample Import Files

#### Using the Web Interface:
1. Go to `/users/import`
2. Click "Download Template" for your preferred format
3. Edit the downloaded file with your data

#### Using Command Line (for testing):
```bash
# Create a simple test file
php artisan tinker --execute="
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
\$s = new Spreadsheet();
\$sheet = \$s->getActiveSheet();
\$sheet->setCellValue('A1', 'Name*');
\$sheet->setCellValue('B1', 'Email*');
\$sheet->setCellValue('C1', 'Password');
\$sheet->setCellValue('D1', 'Campus Codes');
\$sheet->setCellValue('E1', 'Role Codes');
\$sheet->setCellValue('A2', 'Test User');
\$sheet->setCellValue('B2', 'test@example.com');
\$sheet->setCellValue('C2', 'password123');
\$sheet->setCellValue('D2', 'HN');
\$sheet->setCellValue('E2', 'can_bo_dao_tao');
\$writer = new Xlsx(\$s);
\$writer->save('storage/app/temp/sample_import.xlsx');
echo 'Sample file created at storage/app/temp/sample_import.xlsx';
"
```

### 5. Import Process

1. **Upload File**: Drag & drop or select Excel file (max 2MB)
2. **Preview**: Review detected format and first 5 rows
3. **Configure Options**:
   - **Duplicate Handling**: Skip, Update, or Error
   - **Create Missing Campuses**: Yes/No
4. **Process**: Click "Import Users" to start processing
5. **Review Results**: Check success/failure counts and error details

### 6. Import File Requirements

- **File Types**: `.xlsx`, `.xls`
- **File Size**: Maximum 2MB (configurable)
- **Required Headers**: Must include asterisk (*) marked fields
- **Campus Codes**: Must exist in database (HN, HCM, DN, CT)
- **Role Codes**: Must exist in database (use snake_case format)

---

## User Export Functionality

### 1. Access Export

1. Navigate to **Users** list page
2. Click "Export Excel" button
3. Ensure you have `export_user` permission

### 2. Export Process

```bash
# Export is triggered from the users list page
# File is automatically downloaded with timestamp
# Example filename: users_export_2025-05-28_14-30-45.xlsx
```

### 3. Export Format

The exported file includes:
- **User Information**: Name, Email, Phone, Address
- **Campus Assignments**: All campus-role combinations
- **Timestamps**: Created and updated dates

---

## File Management

### 1. File Storage Locations

```bash
# Temporary import files
storage/app/temp/imports/

# Temporary export files  
storage/app/temp/

# Generated templates
storage/app/temp/templates/

# Public files (if any)
storage/app/public/
```

### 2. File Cleanup

```bash
# Import files are automatically cleaned up after processing
# Export files remain in temp directory

# Manual cleanup (if needed)
rm storage/app/temp/imports/*
rm storage/app/temp/users_export_*.xlsx
```

### 3. Check File Locations

```bash
# List import files
ls -la storage/app/temp/imports/

# List export files
ls -la storage/app/temp/users_export_*.xlsx

# Check storage permissions
ls -la storage/app/temp/
```

### 4. Storage Permissions

```bash
# Ensure proper permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# If using Docker or different user
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data bootstrap/cache/
```

---

## Troubleshooting

### Common Issues

#### 1. "Failed to store uploaded file"
```bash
# Check PHP upload limits
php -i | grep -E "(upload_max_filesize|post_max_size)"

# Check storage permissions
ls -la storage/app/temp/imports/
chmod 755 storage/app/temp/imports/
```

#### 2. "Missing required headers"
- Ensure headers match exactly (case-sensitive)
- Required fields must have asterisk (*) in template
- Clean headers by removing extra spaces

#### 3. "Role with code 'xxx' not found"
```bash
# Check available role codes
php artisan tinker --execute="App\Models\Role::all(['name', 'code'])->each(function(\$r) { echo \$r->name . ' -> ' . \$r->code . PHP_EOL; });"
```

#### 4. "Campus with code 'xxx' not found"
```bash
# Check available campus codes
php artisan tinker --execute="App\Models\Campus::all(['name', 'code'])->each(function(\$c) { echo \$c->name . ' -> ' . \$c->code . PHP_EOL; });"
```

#### 5. Permission Denied
```bash
# Check user permissions
php artisan tinker --execute="
\$user = App\Models\User::find(1);
echo 'User permissions: ';
print_r(\$user->getAllPermissions());
"

# Assign import/export permissions
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'your@email.com')->first();
// Check campus roles and permissions
"
```

### Debug Commands

```bash
# Check import configuration
php artisan tinker --execute="print_r(config('import'));"

# Test file upload directory
php artisan tinker --execute="
echo 'Upload dir: ' . storage_path('app/temp/imports') . PHP_EOL;
echo 'Writable: ' . (is_writable(storage_path('app/temp/imports')) ? 'Yes' : 'No') . PHP_EOL;
"

# Clear caches
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### Log Files

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Filter for import/export errors
grep -i "import\|export\|upload" storage/logs/laravel.log
```

---

## Quick Reference

### Available Campus Codes
- `HN` - Swinburne Hà Nội
- `HCM` - Swinburne Hồ Chí Minh  
- `DN` - Swinburne Đà Năng
- `CT` - Swinburne Cần Thơ

### Available Role Codes
- `super_admin` - Super Admin
- `giam_doc_dao_tao` - Giám Đốc Đào Tạo
- `truong_phong_student_hq` - Trường Phòng Student HQ
- `truong_phong_hanh_chinh` - Trường Phòng Hành Chính
- `truong_ban_tuyen_sinh` - Trường Ban Tuyển Sinh
- `truong_phong_tuyen_sinh` - Trường Phòng Tuyển Sinh
- `can_bo_dao_tao` - Cán Bộ Đào Tạo
- `can_bo_student_hq` - Cán Bộ Student HQ
- `can_bo_hanh_chinh` - Cán Bộ Hành Chính
- `can_bo_tuyen_sinh` - Cán Bộ Tuyển Sinh
- `giang_vien` - Giảng Viên

### Key URLs
- Login: `/login`
- Users List: `/users`
- Import Users: `/users/import`
- Download Templates: `/users/templates/{format}`

### Default Admin Account
- **Email**: `hieupv2412@gmail.com`
- **Password**: `123456`

---

*For additional support, contact the development team or refer to the Laravel documentation.* 
