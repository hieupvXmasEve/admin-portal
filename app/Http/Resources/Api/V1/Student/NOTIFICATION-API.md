# Student Notification API (V2)

API dành cho **Student Portal** – lấy, đánh dấu đọc và xóa thông báo. Response dùng model **NotificationMessage** (V2) và trả đủ trường hiển thị (category + UI hints: color, icon, badges) để FE đồng bộ UI.

**Base URL:** `/api/v1/student/notifications`  
**Auth:** Bearer token (Sanctum), guard `student_or_parent`.

---

## Response envelope

- List (index): paginated với `data`, `meta` (pagination + `unread_count`).
- Các action khác: `success`, `data`, `message`.

---

## Endpoints

### 1. Danh sách thông báo (paginated)

**GET** `/api/v1/student/notifications`

**Query (optional):**

| Param      | Type    | Mô tả                                                |
|-----------|---------|------------------------------------------------------|
| category  | string  | Lọc theo category: `assessment`, `grade`, `attendance`, `enrollment`, `academic`, `system`, `announcement` |
| type      | string  | Lọc theo type_key (max 50 ký tự)                     |
| priority  | string  | `low`, `medium`, `high`, `urgent`                    |
| is_read   | boolean | `true` / `false`                                    |
| date_from | date    | ISO date                                             |
| date_to   | date    | ISO date, >= date_from                              |
| page      | integer | Trang (mặc định 1)                                   |
| per_page  | integer | Số bản ghi/trang (5–100, mặc định 20)               |

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Deadline nộp bài",
      "message": "Bài tập môn X hạn nộp 15/03.",
      "category": {
        "key": "academic",
        "display": "Academic"
      },
      "type_key": "deadline",
      "event_name": "assignment.deadline",
      "is_read": false,
      "is_important": false,
      "time_ago": "2 hours ago",
      "timestamps": {
        "created_at": "2026-03-04T10:00:00.000000Z",
        "read_at": null,
        "expires_at": "2026-03-15T23:59:59.000000Z"
      },
      "data": {
        "category": "academic",
        "is_important": false,
        "days_until_due": 2
      },
      "ui": {
        "icon": "clock",
        "color": "#06b6d4",
        "badges": [
          { "type": "unread", "text": "New", "color": "#3b82f6" }
        ]
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "unread_count": 5
  }
}
```

**Notification item (V2) – dùng cho FE:**

| Field           | Type    | Mô tả |
|-----------------|---------|--------|
| id              | int     | ID notification_message |
| title           | string  | Tiêu đề |
| message         | string  | Nội dung (body) |
| category        | object  | `key`, `display` – phân loại logic/semantic |
| type_key        | string  | Loại notification (deadline, grade_release, manual_notification, …) |
| event_name      | string  | Tên event domain (nếu có) |
| is_read         | bool    | Đã đọc chưa |
| is_important    | bool    | Đánh dấu quan trọng (từ data) |
| time_ago        | string  | "2 hours ago" (human) |
| timestamps      | object  | `created_at`, `read_at`, `expires_at` (ISO) |
| data            | object  | Payload tùy type (category, is_important, days_until_due, …) |
| ui              | object  | Gợi ý UI: `icon`, `color`, `badges` (type, text, color) |

**Category color mapping (đồng bộ với backend):**

| key            | color     | icon           |
|----------------|-----------|----------------|
| academic       | #06b6d4   | book-open      |
| system         | #6b7280   | cog-6-tooth    |
| finance        | #22c55e   | banknotes      |
| personal       | #8b5cf6   | user           |
| event          | #f59e0b   | calendar-days  |
| club           | #3b82f6   | user-group     |
| administrative | #ef4444   | building-office|
| assessment     | #06b6d4   | clipboard-list |
| grade          | #22c55e   | star           |
| attendance     | #8b5cf6   | check-square   |
| enrollment     | #3b82f6   | user-plus      |
| announcement   | #f59e0b   | megaphone      |
| (default)      | #6b7280   | bell           |

**Badges:** FE có thể dùng `ui.badges` để hiển thị chip (New, Important, Expired, Due Today, Financial, …) với `text` và `color` cho từng item.

---

### 2. Summary

**GET** `/api/v1/student/notifications/summary`

**Response (200):**

```json
{
  "success": true,
  "data": {
    "total_notifications": 50,
    "unread_notifications": 5,
    "today_notifications": 3,
    "read_percentage": 90.0
  }
}
```

---

### 3. Đánh dấu một thông báo đã đọc

**POST** `/api/v1/student/notifications/{notification}/mark-read`

`{notification}`: ID notification_message.

**Response (200):**

```json
{
  "success": true,
  "data": { "unread_count": 4 },
  "message": "Notification marked as read"
}
```

**Response (4xx):** Notification không thuộc user hoặc đã đọc – message lỗi trong envelope.

---

### 4. Đánh dấu nhiều thông báo đã đọc

**POST** `/api/v1/student/notifications/mark-multiple-read`

**Body:**

```json
{
  "notification_ids": [1, 2, 3]
}
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "updated_count": 3,
    "unread_count": 2
  },
  "message": "Marked 3 notifications as read"
}
```

---

### 5. Đánh dấu tất cả đã đọc

**POST** `/api/v1/student/notifications/mark-all-read`

**Response (200):**

```json
{
  "success": true,
  "data": {
    "updated_count": 5,
    "unread_count": 0
  },
  "message": "Marked all 5 notifications as read"
}
```

---

### 6. Archive (xóa với user)

**DELETE** `/api/v1/student/notifications/{notification}`

**Response (200):**

```json
{
  "success": true,
  "data": null,
  "message": "Notification archived successfully"
}
```

**Response (4xx):** Notification không tồn tại hoặc đã archive.

---

## Cập nhật UI Frontend

- Dùng **ui.color** cho màu icon/dot/border (theo bảng mapping category ở trên).
- Dùng **ui.icon** cho icon (lucide/heroicons key).
- Hiển thị badge từ **ui.badges** (type, text, color).
- **is_read** để style đã đọc (opacity, font-weight).
- **timestamps.created_at** / **time_ago** cho thời gian.
- **data** dùng cho deep link hoặc chi tiết (e.g. `days_until_due`, link).

Resource class: `App\Http\Resources\Api\V1\Student\NotificationResource`.
