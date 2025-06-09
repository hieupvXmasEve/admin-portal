# 🎓 Swinburne Project Management System

Modern web application built with Laravel 12 + Vue.js 3 + Inertia.js

## 🚀 Quick Start

### Local Development (Recommended)
```bash
composer dev  # Start all services
```

### Docker Compose Development
```bash
cp env.docker.example .env
npm run docker:up
```

## 🔗 Documentation

- **[📋 Quick Start Guide](QUICKSTART.md)** - Essential commands and workflow
- **[🐳 Docker Compose Guide](DOCKER-COMPOSE.md)** - Complete Docker development setup
- **[🧪 Testing Guide](TESTING.md)** - Comprehensive testing setup and pipeline
- **[🚀 Deployment Guide](DEPLOYMENT.md)** - Production deployment to Google Cloud

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Vue.js 3, TypeScript, TailwindCSS
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
