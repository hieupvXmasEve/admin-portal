# Student Wallet API Documentation

## Tổng quan

Student Wallet API cung cấp hệ thống quản lý số dư Gold cho sinh viên với đầy đủ tính năng theo dõi, lịch sử giao dịch và quản lý bằng staff. Hệ thống này được thiết kế để đảm bảo tính minh bạch và khả năng kiểm tra (audit) cho mọi thay đổi về số dư Gold.

## Kiến trúc API

## ADMIN API ENDPOINTS

### 1. Xem ví của sinh viên cụ thể

**Endpoint:** `GET /api/wallet/students/{student}/`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.show`  
**Permission:** `view_student_wallet` hoặc `view_any_student_wallet`

**Mục đích:**
- Staff xem thông tin ví của sinh viên bất kỳ
- Hỗ trợ sinh viên khi có thắc mắc về số dư
- Quản lý tài khoản sinh viên

**Response:**
```json
{
  "id": 1,
  "student_id": 123,
  "balance": "150.50",
  "balance_raw": 150.5,
  "updated_at": "2024-01-15T10:30:00Z",
  "formatted_updated_at": "Jan 15, 2024 10:30 AM",
  "student": {
    "id": 123,
    "student_id": "SWU2024001",
    "full_name": "Nguyen Van A",
    "email": "student@example.com"
  }
}
```


**Sử dụng trong Frontend:**
- Trang quản lý sinh viên
- Student profile trong admin panel
- Customer support interface

### 2. Xem tóm tắt ví sinh viên (Admin)

**Endpoint:** `GET /api/wallet/students/{student}/summary`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.summary`  
**Permission:** `view_any_student_wallet`

**Mục đích:**
- Cung cấp cái nhìn tổng quan cho staff
- Hỗ trợ phân tích và báo cáo
- Theo dõi hoạt động tài chính sinh viên

**Response:**
```json
{
  "wallet": {
    "id": 1,
    "balance": "150.50",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "stats": {
    "total_earned": 200.75,
    "total_spent": 50.25,
    "total_adjustments": 0,
    "transaction_count": 15,
    "current_balance": "150.50"
  },
  "recent_transactions": [
    {
      "id": 1,
      "amount": "+10.00",
      "type": "earn",
      "source_type": "event",
      "notes": "Event participation reward",
      "created_at": "2024-01-15T09:00:00Z"
    }
  ]
}
```

**Sử dụng trong Frontend:**
- Admin dashboard
- Student financial overview
- Reporting tools

### 3. Điều chỉnh số dư ví

**Endpoint:** `POST /api/wallet/students/{student}/adjust`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.adjust`  
**Permission:** `adjust_wallet_balance`

**Mục đích:**
- **Quan trọng nhất**: Cho phép staff thực hiện điều chỉnh thủ công số dư
- Xử lý các trường hợp đặc biệt (lỗi hệ thống, hoàn tiền, etc.)
- Tạo audit trail cho mọi thay đổi thủ công

**Request Body:**
```json
{
  "amount": 50.00,
  "notes": "Refund for cancelled event - Ticket #12345"
}
```

**Response:**
```json
{
  "message": "Wallet balance adjusted successfully.",
  "transaction": {
    "id": 15,
    "amount": "+50.00",
    "type": "adjust",
    "source_type": "manual",
    "notes": "Refund for cancelled event - Ticket #12345",
    "created_at": "2024-01-15T14:30:00Z"
  },
  "wallet": {
    "id": 1,
    "balance": "200.50",
    "updated_at": "2024-01-15T14:30:00Z"
  }
}
```

**Validation Rules:**
- `amount`: Required, numeric, không được bằng 0, trong khoảng -999,999.99 đến 999,999.99
- `notes`: Required, string, tối đa 1000 ký tự

**Sử dụng trong Frontend:**
- Modal điều chỉnh số dư trong admin panel
- Bulk adjustment tools
- Customer support interface

### 4. Kiểm tra số dư

**Endpoint:** `POST /api/wallet/students/{student}/check-balance`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.check-balance`

**Mục đích:**
- Kiểm tra xem sinh viên có đủ số dư cho một giao dịch không
- Validation trước khi thực hiện các hoạt động tốn Gold
- Prevent overdraft và số dư âm

**Request Body:**
```json
{
  "amount": 75.50
}
```

**Response:**
```json
{
  "has_sufficient_balance": true,
  "current_balance": "150.50",
  "requested_amount": "75.50"
}
```

**Sử dụng trong Frontend:**
- Pre-validation trong forms
- Real-time balance checking
- Shopping cart validation

### 5. Xem lịch sử giao dịch sinh viên (Admin)

**Endpoint:** `GET /api/wallet/students/{student}/transactions/`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.transactions.index`  
**Permission:** `view_any_wallet_transaction`

**Mục đích:**
- Staff xem toàn bộ lịch sử giao dịch của sinh viên
- Hỗ trợ troubleshooting và customer support
- Audit và compliance checking

**Query Parameters:** Tương tự như student endpoint


**Response:**
```json
{
  "id": 1,
  "student_id": 123,
  "amount": "25.00",
  "display_amount": "+25.00",
  "type": "earn",
  "type_label": "Earn",
  "source_type": "event",
  "source_type_label": "Event",
  "source_id": 456,
  "notes": "Participation in Career Fair 2024",
  "created_at": "2024-01-15T09:00:00Z",
  "formatted_created_at": "Jan 15, 2024 9:00 AM",
  "is_positive": true,
  "absolute_amount": "25.00"
}
```

**Sử dụng trong Frontend:**
- Admin transaction management
- Customer support tools
- Audit reports

### 6. Xem giao dịch gần đây sinh viên (Admin)

**Endpoint:** `GET /api/wallet/students/{student}/transactions/recent`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.transactions.recent`  
**Permission:** `view_any_wallet_transaction`

**Mục đích:**
- Quick view hoạt động gần đây của sinh viên
- Dashboard monitoring
- Customer support context

**Response:** Tương tự như student recent transactions

### 7. Xem thống kê giao dịch sinh viên (Admin)

**Endpoint:** `GET /api/wallet/students/{student}/transactions/stats`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.students.transactions.stats`  
**Permission:** `view_any_wallet_transaction`

**Mục đích:**
- Phân tích tài chính sinh viên
- Báo cáo và thống kê
- Business intelligence

**Response:** Tương tự như student stats

### 8. Xem chi tiết giao dịch (Admin)

**Endpoint:** `GET /api/wallet/transactions/{transaction}`  
**Middleware:** `web`  
**Route Name:** `api.admin.wallet.transactions.show`  
**Permission:** `view_wallet_transaction` hoặc `view_any_wallet_transaction`

**Mục đích:**
- Staff xem chi tiết bất kỳ giao dịch nào
- Audit trail và investigation
- Dispute resolution

**Response:** Tương tự như student transaction detail

## Bảo mật và Phân quyền

### Student Access
- **Authentication**: Yêu cầu đăng nhập qua Sanctum token
- **Authorization**: Sinh viên chỉ có thể xem thông tin ví của chính mình
- **Rate Limiting**: Áp dụng rate limiting để tránh spam

### Admin Access  
- **Authentication**: Session-based authentication qua web middleware
- **Authorization**: Kiểm tra permissions cụ thể:
  - `view_student_wallet`: Xem ví sinh viên
  - `view_any_student_wallet`: Xem ví bất kỳ sinh viên nào
  - `adjust_wallet_balance`: Điều chỉnh số dư
  - `view_wallet_transaction`: Xem giao dịch
  - `view_any_wallet_transaction`: Xem bất kỳ giao dịch nào

### Audit Trail
- Mọi thay đổi số dư đều được log trong `gold_transactions`
- Ghi nhận user thực hiện điều chỉnh (trong `source_id` khi `source_type = 'manual'`)
- Timestamp chính xác cho mọi giao dịch
- Notes bắt buộc cho manual adjustments

## Error Handling

### Common Error Responses

**400 Bad Request:**
```json
{
  "message": "Validation failed",
  "errors": {
    "amount": ["Amount must be a non-zero number"],
    "notes": ["Notes are required"]
  }
}
```

**401 Unauthorized:**
```json
{
  "message": "Unauthenticated"
}
```

**403 Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

**404 Not Found:**
```json
{
  "message": "Student not found"
}
```

**422 Unprocessable Entity:**
```json
{
  "message": "Insufficient wallet balance"
}
```

## Tích hợp Frontend

### Recommended Usage Patterns

1. **Dashboard Components:**
   - Sử dụng summary endpoint để hiển thị overview
   - Recent transactions cho activity feed
   - Stats cho các metrics cards

2. **Transaction Management:**
   - Paginated history cho data tables
   - Filter/search functionality
   - Real-time balance updates

3. **Admin Tools:**
   - Balance adjustment modals
   - Student wallet search
   - Bulk operations support

4. **Customer Support:**
   - Quick student lookup
   - Transaction investigation tools
   - Adjustment tracking

## Performance Considerations

- **Database Indexing**: Optimized queries với indexes trên `student_id`, `created_at`
- **Caching**: Consider caching wallet balances for frequently accessed data
- **Pagination**: All list endpoints support pagination để tránh large datasets
- **Rate Limiting**: Implement appropriate rate limits cho public endpoints
