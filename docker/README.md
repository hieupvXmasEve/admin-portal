# Docker Configuration Files

This directory contains Docker configuration files for the Swinx application.

## 📁 Directory Structure

```
docker/
├── mysql/                          # MySQL database configuration
│   ├── init.sql                    # Database initialization script
│   ├── init-test.sql              # Test database initialization
│   └── my.cnf                     # MySQL configuration
├── php/                           # PHP configuration files
│   ├── php.ini                    # Development PHP settings
│   └── production.ini             # Production PHP settings
├── start-frankenphp.sh           # FrankenPHP startup script (development)
├── start-frankenphp-production.sh # FrankenPHP startup script (production)
└── README.md                      # This file
```

## 🔧 Configuration Files

### MySQL Configuration
- **`mysql/init.sql`** - Creates initial database structure and users for development
- **`mysql/init-test.sql`** - Creates test database structure for testing
- **`mysql/my.cnf`** - MySQL server configuration optimized for Laravel

### PHP Configuration
- **`php/php.ini`** - Development PHP settings with debugging enabled
- **`php/production.ini`** - Production PHP settings optimized for performance

### FrankenPHP Scripts
- **`start-frankenphp.sh`** - Development startup script with Laravel optimizations
- **`start-frankenphp-production.sh`** - Production startup script with security hardening

## 🚀 Usage

These files are automatically used by the Docker Compose configurations:

- **Development**: `docker-compose.dev.yml` uses development configurations
- **Local Production**: `docker-compose.local-prod.yml` uses production configurations  
- **Production**: `docker-compose.production.yml` uses production configurations

## 📝 Customization

To customize configurations:

1. **MySQL Settings**: Edit `mysql/my.cnf`
2. **PHP Settings**: Edit `php/php.ini` or `php/production.ini`
3. **Startup Behavior**: Edit the appropriate startup script

Changes will be applied when containers are rebuilt:

```bash
# Development
./dev.sh rebuild

# Local Production
./local-prod.sh rebuild

# Production
./prod.sh deploy
```

## 🔒 Security Notes

- Production configurations disable debugging and enable security features
- Database passwords should be set via environment variables, not in these files
- PHP production settings optimize for performance and security

## 📚 Related Documentation

- [Main Docker Guide](../DOCKER_GUIDE.md) - Complete environment management
- [FrankenPHP Migration Guide](../FRANKENPHP_MIGRATION_GUIDE.md) - FrankenPHP configuration details
