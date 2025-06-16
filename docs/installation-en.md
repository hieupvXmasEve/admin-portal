# 🚀 Installation and Setup Guide

Complete installation guide for the Swinburne Project Management System.

## 📋 Prerequisites

### System Requirements
- **PHP**: 8.4 or higher
- **Node.js**: 18.x or higher
- **Composer**: Latest version
- **Database**: MySQL 8.0 or MariaDB 10.6+
- **Web Server**: FrankenPHP (recommended) or Nginx/Apache

### Development Tools (Recommended)
- **Laravel Herd**: For local development environment
- **Docker**: For containerized development
- **Git**: Version control
- **VS Code**: With PHP and Vue.js extensions

## 🐳 Quick Start with Docker (Recommended)

### Development Environment
```bash
# Clone the repository
git clone <repository-url>
cd swinx

# Start development environment
./dev.sh start

# Access the application
# URL: http://localhost:8080
```

### Local Production Testing
```bash
# Start local production environment
./local-prod.sh start

# Access the application
# URL: http://localhost:8080
```

### Production Deployment
```bash
# Deploy to production
./prod.sh deploy
```

## 🛠️ Traditional Installation

### 1. Clone and Install Dependencies

```bash
# Clone the repository
git clone <repository-url>
cd swinx

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 2. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Configure Environment Variables

Edit `.env` file with your configuration:

```env
# Application
APP_NAME="Swinburne Project Management"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swinx
DB_USERNAME=your_username
DB_PASSWORD=your_password

# File Upload
IMPORT_MAX_FILE_SIZE=2MB
```

### 4. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE swinx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed
```

### 5. Build Frontend Assets

```bash
# Development build with hot reload
npm run dev

# Production build
npm run build
```

### 6. Start Development Server

```bash
# Using Laravel's built-in server
php artisan serve

# Or using Composer script
composer dev
```

## 🔧 Advanced Configuration

### File Storage Setup

```bash
# Create storage link
php artisan storage:link

# Set proper permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### Queue Configuration (Optional)

```bash
# For background job processing
php artisan queue:work
```

### Cache Configuration

```bash
# Clear all caches
php artisan optimize:clear

# Optimize for production
php artisan optimize
```

## 🧪 Verify Installation

### Run Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
```

### Check System Status
```bash
# Check Laravel installation
php artisan about

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo();"
```

## 🚨 Troubleshooting

### Common Issues

**Permission Errors:**
```bash
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data bootstrap/cache/
```

**Database Connection Issues:**
- Verify database credentials in `.env`
- Ensure MySQL service is running
- Check firewall settings

**Asset Build Issues:**
```bash
# Clear Node modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run build
```

**Cache Issues:**
```bash
# Clear all Laravel caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## 🔗 Next Steps

After successful installation:

1. **[Development Guide](development-en.md)** - Learn development standards and workflows
2. **[Deployment Guide](deployment-en.md)** - Deploy to production environments
3. **[User Guide](user-guide-en.md)** - Understand system functionality
4. **[API Documentation](api-documentation-en.md)** - Integrate with the system

## 📞 Support

For installation issues:
- Check the [Troubleshooting Section](#-troubleshooting)
- Review [Development Guidelines](development-en.md)
- Contact the development team

---

*Last updated: {{ date('Y-m-d') }}*
