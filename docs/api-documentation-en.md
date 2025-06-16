# 🔌 API Documentation

Complete API documentation for the Swinburne Project Management System.

## 📋 Overview

The Swinburne Project Management System provides a RESTful API for integration with external systems. The API follows Laravel conventions and uses JSON for data exchange.

## 🔐 Authentication

### API Token Authentication
The system uses Laravel Sanctum for API authentication:

```bash
# Login to get token
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

# Response
{
  "token": "your-api-token",
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com"
  }
}
```

### Using API Token
Include the token in the Authorization header:

```bash
Authorization: Bearer your-api-token
```

## 📊 Response Format

### Standard Response Structure
```json
{
  "success": true,
  "data": {},
  "message": "Operation successful",
  "errors": null
}
```

### Error Response Structure
```json
{
  "success": false,
  "data": null,
  "message": "Error message",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### Pagination Response
```json
{
  "success": true,
  "data": {
    "data": [],
    "meta": {
      "current_page": 1,
      "total": 100,
      "per_page": 15,
      "last_page": 7
    }
  }
}
```

## 👥 User Management API

### List Users
```bash
GET /api/users
Authorization: Bearer your-api-token

# Query Parameters
?page=1&per_page=15&search=john&campus=HN&role=admin
```

### Get User Details
```bash
GET /api/users/{id}
Authorization: Bearer your-api-token
```

### Create User
```bash
POST /api/users
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "phone": "+1234567890",
  "address": "123 Main St",
  "campus_roles": [
    {
      "campus_code": "HN",
      "role_code": "can_bo_dao_tao"
    }
  ]
}
```

### Update User
```bash
PUT /api/users/{id}
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "John Doe Updated",
  "phone": "+1234567891",
  "campus_roles": [
    {
      "campus_code": "HN",
      "role_code": "giang_vien"
    }
  ]
}
```

### Delete User
```bash
DELETE /api/users/{id}
Authorization: Bearer your-api-token
```

## 🏢 Campus Management API

### List Campuses
```bash
GET /api/campuses
Authorization: Bearer your-api-token
```

### Get Campus Details
```bash
GET /api/campuses/{id}
Authorization: Bearer your-api-token
```

### Create Campus
```bash
POST /api/campuses
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "Swinburne New Campus",
  "code": "NC",
  "address": "456 University Ave",
  "phone": "+1234567890",
  "email": "newcampus@swinburne.edu"
}
```

## 🎭 Role Management API

### List Roles
```bash
GET /api/roles
Authorization: Bearer your-api-token
```

### Get Role Details
```bash
GET /api/roles/{id}
Authorization: Bearer your-api-token
```

### Create Role
```bash
POST /api/roles
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "New Role",
  "code": "new_role",
  "description": "Description of the new role",
  "permissions": ["permission1", "permission2"]
}
```

## 📤 Import/Export API

### Import Users
```bash
POST /api/users/import
Authorization: Bearer your-api-token
Content-Type: multipart/form-data

file: [Excel file]
format: "simple" | "detailed" | "relationship"
duplicate_handling: "skip" | "update" | "error"
create_missing_campuses: true | false
```

### Export Users
```bash
GET /api/users/export
Authorization: Bearer your-api-token

# Query Parameters
?format=excel&campus=HN&role=admin
```

### Import Status
```bash
GET /api/imports/{import_id}/status
Authorization: Bearer your-api-token
```

## 📋 Data Models

### User Model
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "address": "123 Main St",
  "email_verified_at": "2023-01-01T00:00:00Z",
  "created_at": "2023-01-01T00:00:00Z",
  "updated_at": "2023-01-01T00:00:00Z",
  "campus_roles": [
    {
      "campus": {
        "id": 1,
        "name": "Swinburne Hà Nội",
        "code": "HN"
      },
      "role": {
        "id": 1,
        "name": "Cán Bộ Đào Tạo",
        "code": "can_bo_dao_tao"
      },
      "assigned_at": "2023-01-01T00:00:00Z"
    }
  ]
}
```

### Campus Model
```json
{
  "id": 1,
  "name": "Swinburne Hà Nội",
  "code": "HN",
  "address": "123 University St",
  "phone": "+84123456789",
  "email": "hanoi@swinburne.edu",
  "created_at": "2023-01-01T00:00:00Z",
  "updated_at": "2023-01-01T00:00:00Z"
}
```

### Role Model
```json
{
  "id": 1,
  "name": "Cán Bộ Đào Tạo",
  "code": "can_bo_dao_tao",
  "description": "Training officer role",
  "created_at": "2023-01-01T00:00:00Z",
  "updated_at": "2023-01-01T00:00:00Z",
  "permissions": [
    {
      "id": 1,
      "name": "view_users",
      "display_name": "View Users"
    }
  ]
}
```

## ⚠️ Error Codes

### HTTP Status Codes
- **200**: Success
- **201**: Created
- **400**: Bad Request
- **401**: Unauthorized
- **403**: Forbidden
- **404**: Not Found
- **422**: Validation Error
- **500**: Internal Server Error

### Custom Error Codes
- **USER_NOT_FOUND**: User with specified ID not found
- **CAMPUS_NOT_FOUND**: Campus with specified code not found
- **ROLE_NOT_FOUND**: Role with specified code not found
- **DUPLICATE_EMAIL**: Email address already exists
- **INVALID_FILE_FORMAT**: Uploaded file format not supported
- **IMPORT_VALIDATION_FAILED**: Import data validation failed

## 🔒 Rate Limiting

API endpoints are rate limited to prevent abuse:
- **Authentication endpoints**: 5 requests per minute
- **General API endpoints**: 60 requests per minute
- **Import/Export endpoints**: 10 requests per minute

## 📝 Examples

### Complete User Creation Example
```bash
curl -X POST https://your-domain.com/api/users \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "securepassword123",
    "phone": "+84987654321",
    "address": "456 Academic Road",
    "campus_roles": [
      {
        "campus_code": "HCM",
        "role_code": "giang_vien"
      },
      {
        "campus_code": "HN",
        "role_code": "can_bo_dao_tao"
      }
    ]
  }'
```

### Bulk User Import Example
```bash
curl -X POST https://your-domain.com/api/users/import \
  -H "Authorization: Bearer your-api-token" \
  -F "file=@users_import.xlsx" \
  -F "format=simple" \
  -F "duplicate_handling=update" \
  -F "create_missing_campuses=false"
```

## 🧪 Testing

### API Testing Tools
- **Postman**: Import collection from `/api/documentation/postman`
- **Insomnia**: Import workspace from `/api/documentation/insomnia`
- **cURL**: Use examples provided in this documentation

### Test Environment
- **Base URL**: `https://your-domain.com/api`
- **Test Token**: Use login endpoint to get valid token
- **Test Data**: Use seeded data for testing

## 📞 Support

For API integration support:
- **Documentation**: This guide and inline API docs
- **Postman Collection**: Available at `/api/documentation/postman`
- **Technical Support**: Contact development team
- **Rate Limit Issues**: Contact administrator for increased limits

---

*Last updated: {{ date('Y-m-d') }}*
