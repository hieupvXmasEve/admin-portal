# 🎓 Swinburne Project Management System

Modern web application built with Laravel 12 + Vue.js 3 + Inertia.js

## 🚀 Quick Start

### Docker Development (Recommended)
```bash
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

## 🔗 Documentation

- **[🐳 Docker Guide](DOCKER_GUIDE.md)** - Complete Docker environment management (Development, Local Production, Production)
- **[🧟 FrankenPHP Migration Guide](FRANKENPHP_MIGRATION_GUIDE.md)** - FrankenPHP web server configuration
- **[🚀 Deployment Guide](DEPLOYMENT.md)** - Production deployment to Google Cloud
- **[📋 Step-by-Step Deploy](STEP_BY_STEP_DEPLOY.md)** - Detailed deployment instructions

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Vue.js 3, TypeScript, TailwindCSS
- **Web Server**: FrankenPHP (modern PHP application server)
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Development**: Docker Compose, Vite HMR

---

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

---

**Start developing**: `composer dev` hoặc `npm run docker:up` 🚀
