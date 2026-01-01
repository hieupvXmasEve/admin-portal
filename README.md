# 🎓 Swinburne Project Management System

Modern web application built with Laravel 12 + Vue.js 3 + Inertia.js for comprehensive educational management.

## 🚀 Quick Start

### Docker Development (Recommended)

```bash
mkdir -p storage/app/private/email-attachments
php -v
php -m | grep -E 'pdo|mbstring|openssl|curl|zip|imagick'
cp .env.example .env  # nếu chưa có
php artisan key:generate --ansi
chmod -R ug+rw storage bootstrap/cache
php artisan storage:link

composer install --no-dev --prefer-dist --optimize-autoloader
composer dump-autoload

npm ci
npm run build
# Nếu dùng SSR:
# npm run build:ssr

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# Update permissions
php artisan db:seed --class=UpdatePermissionsSeeder

#•  Production nên dùng Supervisor/systemd. Ví dụ Supervisor (/etc/supervisor/conf.d/laravel-worker.conf):
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/your-app/artisan queue:work --queue=emails,bulk-emails,notifications --sleep=1 --tries=5 --timeout=120
autostart=true
autorestart=true
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-worker.log
stopwaitsecs=3600

php artisan migrate:status
php artisan about
php artisan queue:retry all   # nếu cần retry
# Development environment
./dev.sh start

# Local production testing
./local-prod.sh start

# Production deployment
./prod.sh deploy
```

### Traditional Development

```bash
composer dev  # Start all services
```

## 📚 Documentation

### 🌐 Choose Your Language

**English Documentation:**

- **[📥 Installation Guide](docs/installation-en.md)** - Complete setup and installation
- **[🛠️ Development Guide](docs/development-en.md)** - Development standards and workflows
- **[🚀 Deployment Guide](docs/deployment-en.md)** - Production deployment with Docker
- **[👥 User Guide](docs/user-guide-en.md)** - System functionality and usage
- **[🔌 API Documentation](docs/api-documentation-en.md)** - RESTful API integration

**Vietnamese Documentation (Tài liệu tiếng Việt):**

- **[📥 Hướng Dẫn Cài Đặt](docs/installation-vi.md)** - Thiết lập và cài đặt hoàn chỉnh
- **[🛠️ Hướng Dẫn Phát Triển](docs/development-vi.md)** - Tiêu chuẩn và quy trình phát triển
- **[🚀 Hướng Dẫn Triển Khai](docs/deployment-vi.md)** - Triển khai production với Docker
- **[👥 Hướng Dẫn Người Dùng](docs/user-guide-vi.md)** - Chức năng và cách sử dụng hệ thống
- **[🔌 Tài Liệu API](docs/api-documentation-vi.md)** - Tích hợp RESTful API

**[📖 Complete Documentation Index](docs/README.md)**

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Vue.js 3, TypeScript, TailwindCSS
- **Web Server**: FrankenPHP (modern PHP application server)
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Development**: Docker Compose, Vite HMR

## ✨ Key Features

- **Multi-campus Support**: Manage multiple university campuses
- **Role-based Access Control**: Granular permissions system
- **User Management**: Complete CRUD with import/export functionality
- **Modern UI**: Responsive design with Vue.js 3 and TailwindCSS
- **RESTful API**: Full API for external integrations
- **Docker Ready**: Complete containerization for all environments

## 🏗️ System Architecture

```
Internet → FrankenPHP (SSL/HTTP) → Laravel App → MySQL/Redis
                                ↓
                          Queue Workers & Scheduler
```

### Environment Types

- **Development**: HTTP-only, debug enabled, hot reload
- **Local Production**: HTTPS testing, production-like environment
- **Production**: Full HTTPS, SSL certificates, optimized performance

## 🧪 Testing

### Quick Testing

```bash
./scripts/pre-push.sh          # Fast local tests
./scripts/test-local.sh        # Comprehensive Docker tests
npm run test:local             # Same as above
```

### Pre-push Validation

```bash
./scripts/pre-push.sh --docker # Full Docker integration tests
npm run pre-push:docker        # Same as above
```

## 🚀 Getting Started

### For Developers

1. **Setup**: Follow the [Installation Guide](docs/installation-en.md)
2. **Development**: Read the [Development Guide](docs/development-en.md)
3. **Deploy**: Use the [Deployment Guide](docs/deployment-en.md)

### For End Users

1. **Getting Started**: Check the [User Guide](docs/user-guide-en.md)
2. **System Features**: Learn about user management and role-based access
3. **Import/Export**: Understand bulk data operations

### For Integrators

1. **API Integration**: Review the [API Documentation](docs/api-documentation-en.md)
2. **Authentication**: Understand token-based authentication
3. **Data Models**: Learn about system data structures

## 🔧 Development Commands

```bash
# Laravel commands
php artisan serve              # Start development server
php artisan migrate           # Run database migrations
php artisan test             # Run tests

# Frontend commands
npm run dev                  # Development with hot reload
npm run build               # Production build
npm run type-check          # TypeScript checking

# Docker commands
./dev.sh start              # Start development environment
./dev.sh stop               # Stop development environment
./local-prod.sh start       # Start local production testing
./prod.sh deploy            # Deploy to production
```

## 📊 Project Status

- ✅ **User Management System**: Complete with import/export
- ✅ **Role-based Access Control**: Multi-campus support
- ✅ **Modern UI**: Vue.js 3 + TailwindCSS + Reka-ui Vue
- ✅ **Docker Integration**: Development, testing, and production
- ✅ **API Integration**: RESTful API with authentication
- ✅ **Comprehensive Documentation**: Bilingual support

## 🤝 Contributing

1. Read the [Development Guide](docs/development-en.md) for coding standards
2. Follow the established patterns and conventions
3. Write tests for new functionality
4. Update documentation for any changes
5. Submit pull requests for review

## 📞 Support

- **Documentation**: Check the [docs/](docs/) directory
- **Installation Issues**: See [Installation Guide](docs/installation-en.md)
- **Development Questions**: Review [Development Guide](docs/development-en.md)
- **Deployment Problems**: Follow [Deployment Guide](docs/deployment-en.md)
- **User Support**: Refer to [User Guide](docs/user-guide-en.md)

## 📄 License

This project is proprietary software developed for Swinburne University.

---

**Start developing**: `composer dev` or `./dev.sh start` 🚀

_For detailed information, please refer to the comprehensive documentation in the [docs/](docs/) directory._
